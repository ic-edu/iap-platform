<?php

namespace App\Modules\Organization\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationEntitlement;
use App\Modules\Organization\Models\OrganizationMembership;
use App\Modules\Organization\Models\OrganizationSeatAllocation;
use App\Modules\Organization\Services\OrganizationSeatAllocator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class OrganizationEntitlementController extends Controller
{
    public function __construct(
        protected OrganizationSeatAllocator $seatAllocator
    ) {}

    /**
     * Display Organization Seat Entitlements List.
     */
    public function index(Organization $organization): View
    {
        $entitlements = $organization->entitlements()
            ->with(['product', 'orderItem', 'allocations'])
            ->latest()
            ->paginate(15);

        $totalSeatsPurchased = $organization->entitlements()->sum('total_seats');
        $totalSeatsAllocated = OrganizationSeatAllocation::whereHas('entitlement', function ($q) use ($organization) {
            $q->where('organization_id', $organization->id);
        })->where('status', 'active')->count();

        $totalSeatsAvailable = max(0, $totalSeatsPurchased - $totalSeatsAllocated);

        return view('organization::entitlements.index', compact(
            'organization',
            'entitlements',
            'totalSeatsPurchased',
            'totalSeatsAllocated',
            'totalSeatsAvailable'
        ));
    }

    /**
     * Show Entitlement Detail and Manage Seat Allocations.
     */
    public function show(Organization $organization, OrganizationEntitlement $entitlement): View
    {
        if ((string) $entitlement->organization_id !== (string) $organization->id) {
            abort(403, 'Entitlement does not belong to this organization.');
        }

        $entitlement->loadMissing(['product', 'orderItem.order.invoice', 'allocations.membership.user', 'allocations.allocatedBy']);

        $allocations = $entitlement->allocations()
            ->with(['membership.user', 'membership.groups', 'allocatedBy'])
            ->latest()
            ->paginate(20);

        // Fetch eligible candidate members of this organization not yet actively allocated for this entitlement
        $assignedMembershipIds = $entitlement->activeAllocations()->pluck('organization_membership_id');
        $eligibleMemberships = $organization->memberships()
            ->where('status', MembershipStatus::Active)
            ->where('role', MembershipRole::Member)
            ->whereNotIn('id', $assignedMembershipIds)
            ->with(['user', 'groups'])
            ->get();

        return view('organization::entitlements.show', compact(
            'organization',
            'entitlement',
            'allocations',
            'eligibleMemberships'
        ));
    }

    /**
     * Allocate a seat to an eligible candidate member.
     */
    public function allocate(
        Request $request,
        Organization $organization,
        OrganizationEntitlement $entitlement
    ): RedirectResponse {
        if ((string) $entitlement->organization_id !== (string) $organization->id) {
            abort(403, 'Entitlement does not belong to this organization.');
        }

        if ($organization->status !== OrganizationStatus::Active) {
            abort(403, 'Cannot allocate seats. Organization is not active.');
        }

        $validated = $request->validate([
            'membership_id' => ['required', 'exists:organization_memberships,id'],
        ]);

        $membership = OrganizationMembership::findOrFail($validated['membership_id']);

        try {
            $this->seatAllocator->allocate($entitlement, $membership, $request->user());

            return back()->with('status', "Seat successfully allocated to candidate {$membership->user?->name}.");
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Release an active seat allocation.
     */
    public function release(
        Organization $organization,
        OrganizationEntitlement $entitlement,
        OrganizationSeatAllocation $allocation
    ): RedirectResponse {
        if ((string) $entitlement->organization_id !== (string) $organization->id) {
            abort(403, 'Entitlement does not belong to this organization.');
        }

        if ((string) $allocation->organization_entitlement_id !== (string) $entitlement->id) {
            abort(403, 'Allocation does not belong to this entitlement.');
        }

        $candidateName = $allocation->membership?->user?->name ?? 'Candidate';

        $this->seatAllocator->release($allocation, $requestUser = auth()->user());

        return back()->with('status', "Seat allocation for {$candidateName} was released successfully.");
    }
}
