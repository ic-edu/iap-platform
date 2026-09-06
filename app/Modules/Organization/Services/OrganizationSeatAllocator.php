<?php

namespace App\Modules\Organization\Services;

use App\Models\User;
use App\Modules\Organization\Enums\EntitlementStatus;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\SeatAllocationStatus;
use App\Modules\Organization\Models\OrganizationEntitlement;
use App\Modules\Organization\Models\OrganizationMembership;
use App\Modules\Organization\Models\OrganizationSeatAllocation;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrganizationSeatAllocator
{
    /**
     * Allocate an available seat from an active entitlement to a candidate member.
     *
     * @throws InvalidArgumentException
     */
    public function allocate(
        OrganizationEntitlement $entitlement,
        OrganizationMembership $membership,
        User $actor
    ): OrganizationSeatAllocation {
        // 1. Tenant boundary validation
        if ((string) $membership->organization_id !== (string) $entitlement->organization_id) {
            throw new InvalidArgumentException('Cross-organization violation: Candidate does not belong to the entitlement organization.');
        }

        // 2. Entitlement status validation
        if ($entitlement->status !== EntitlementStatus::Active) {
            $statusVal = is_object($entitlement->status) ? $entitlement->status->value : $entitlement->status;
            throw new InvalidArgumentException("Cannot allocate seat. Entitlement is currently {$statusVal}.");
        }

        // 3. Candidate membership status & role validation
        if ($membership->status !== MembershipStatus::Active) {
            throw new InvalidArgumentException('Only active candidate members can be allocated a seat.');
        }

        if ($membership->role !== MembershipRole::Member) {
            throw new InvalidArgumentException('Seats may only be allocated to Candidate Members. Privileged management roles cannot receive seat allocations.');
        }

        // 4. Concurrency-safe allocation within transaction
        return DB::transaction(function () use ($entitlement, $membership, $actor) {
            // Lock entitlement row
            $lockedEntitlement = OrganizationEntitlement::where('id', $entitlement->id)->lockForUpdate()->firstOrFail();

            // Prevent duplicate active allocation for the same candidate and entitlement
            $alreadyAllocated = OrganizationSeatAllocation::where('organization_entitlement_id', $lockedEntitlement->id)
                ->where('organization_membership_id', $membership->id)
                ->where('status', SeatAllocationStatus::Active)
                ->lockForUpdate()
                ->exists();

            if ($alreadyAllocated) {
                throw new InvalidArgumentException("Candidate '{$membership->user?->name}' already has an active seat allocation for this package.");
            }

            // Check capacity
            $activeCount = OrganizationSeatAllocation::where('organization_entitlement_id', $lockedEntitlement->id)
                ->where('status', SeatAllocationStatus::Active)
                ->count();

            if ($activeCount >= $lockedEntitlement->total_seats) {
                throw new InvalidArgumentException('No available seats remaining in this entitlement pool.');
            }

            // Create allocation
            $allocation = OrganizationSeatAllocation::create([
                'organization_entitlement_id' => $lockedEntitlement->id,
                'organization_membership_id'  => $membership->id,
                'allocated_by'                => $actor->id,
                'status'                      => SeatAllocationStatus::Active,
                'allocated_at'                => now(),
                'released_at'                 => null,
            ]);

            ActivityLogger::log(
                action: 'ORG_SEAT_ALLOCATED',
                description: "Allocated 1 seat of '{$lockedEntitlement->product?->title}' to candidate {$membership->user?->name}",
                subject: $allocation,
                properties: [
                    'organization_id'             => $lockedEntitlement->organization_id,
                    'organization_entitlement_id' => $lockedEntitlement->id,
                    'organization_membership_id'  => $membership->id,
                    'candidate_user_id'           => $membership->user_id,
                    'allocated_by'                => $actor->id,
                ]
            );

            return $allocation;
        });
    }

    /**
     * Release an active seat allocation back into the available pool.
     */
    public function release(OrganizationSeatAllocation $allocation, User $actor): OrganizationSeatAllocation
    {
        if ($allocation->status === SeatAllocationStatus::Released) {
            return $allocation;
        }

        return DB::transaction(function () use ($allocation, $actor) {
            $locked = OrganizationSeatAllocation::where('id', $allocation->id)->lockForUpdate()->firstOrFail();

            $locked->update([
                'status'      => SeatAllocationStatus::Released,
                'released_at' => now(),
            ]);

            $entitlement = $locked->entitlement;
            $candidate = $locked->membership?->user;

            ActivityLogger::log(
                action: 'ORG_SEAT_RELEASED',
                description: "Released seat allocation for candidate {$candidate?->name} on '{$entitlement?->product?->title}'",
                subject: $locked,
                properties: [
                    'organization_id'             => $entitlement?->organization_id,
                    'organization_entitlement_id' => $locked->organization_entitlement_id,
                    'organization_membership_id'  => $locked->organization_membership_id,
                    'candidate_user_id'           => $locked->membership?->user_id,
                    'released_by'                 => $actor->id,
                ]
            );

            return $locked;
        });
    }
}
