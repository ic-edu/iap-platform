<?php

namespace App\Services;

use App\Models\User;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\SeatAllocationStatus;
use App\Modules\Organization\Models\OrganizationSeatAllocation;
use Illuminate\Database\Eloquent\Builder;

class AssessmentRequestEligibilityService
{
    /**
     * Determine if a candidate is eligible for an Assessment Request.
     *
     * Eligible if:
     * 1. Candidate is a student AND has direct B2C paid transactions (PaymentStatus::Success / Paid), OR
     * 2. Candidate is a student AND has an active B2B OrganizationSeatAllocation.
     */
    public function isCandidateEligible(User $candidate): bool
    {
        if (!$candidate->hasRole('student')) {
            return false;
        }

        // B2C Direct Paid Transaction
        $hasDirectPayment = User::where('id', $candidate->id)
            ->whereHas('orders.invoice.payments', fn($p) => $p->whereIn('status', [PaymentStatus::Success, PaymentStatus::Paid]))
            ->exists();

        if ($hasDirectPayment) {
            return true;
        }

        // B2B Active Institutional Seat Allocation
        $hasActiveInstitutionalSeat = OrganizationSeatAllocation::where('status', SeatAllocationStatus::Active)
            ->whereHas('membership', function ($m) use ($candidate) {
                $m->where('user_id', $candidate->id)
                  ->where('status', MembershipStatus::Active);
            })
            ->exists();

        return $hasActiveInstitutionalSeat;
    }

    /**
     * Get query builder for all eligible candidates (B2C Paid + B2B Active Seat Allocations).
     *
     * @return Builder<User>
     */
    public function getEligibleCandidatesQuery(): Builder
    {
        return User::role('student')
            ->where('status', 'active')
            ->where(function (Builder $query) {
                $query->whereHas('orders.invoice.payments', fn($p) => $p->whereIn('status', [PaymentStatus::Success, PaymentStatus::Paid]))
                    ->orWhereHas('organizationMemberships', function ($m) {
                        $m->where('status', MembershipStatus::Active)
                            ->whereHas('seatAllocations', fn($sa) => $sa->where('status', SeatAllocationStatus::Active));
                    });
            });
    }
}
