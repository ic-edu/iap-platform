<?php

namespace App\Modules\Assessment\Engines;

use App\Models\User;
use App\Modules\Assessment\Events\AssignmentRevoked;
use App\Modules\Assessment\Events\TestAssigned;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\Payment;
use InvalidArgumentException;

class AssignmentEngine
{
    /**
     * Check if a candidate has a confirmed PAID transaction for a Real Test (Payment Eligibility).
     */
    public function isPaymentEligible(Test $test, User $user, ?Payment $payment = null, ?Order $order = null): bool
    {
        if ($test->isSimulator()) {
            return true;
        }

        if ($payment && ($payment->status === PaymentStatus::Success || $payment->status === PaymentStatus::Paid)) {
            return true;
        }

        if ($order && $order->status === OrderStatus::Completed) {
            return true;
        }

        return Payment::whereHas('invoice.order.items', function ($q) use ($test) {
            $q->whereHas('product', fn($p) => $p->where('test_id', $test->id));
        })
        ->whereIn('status', [PaymentStatus::Success, PaymentStatus::Paid])
        ->whereHas('invoice.order', fn($o) => $o->where('user_id', $user->id))
        ->exists();
    }

    /**
     * Get all candidate users who have paid for this Real Test and are eligible for assignment.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function getEligibleCandidates(Test $test)
    {
        if ($test->isSimulator()) {
            return User::role('student')->get();
        }

        return User::whereHas('orders', function ($q) use ($test) {
            $q->whereHas('items.product', fn($p) => $p->where('test_id', $test->id))
              ->whereHas('invoice.payments', fn($pm) => $pm->whereIn('status', [PaymentStatus::Success, PaymentStatus::Paid]));
        })->get();
    }

    /**
     * Assign a test to a specific student user.
     */
    public function assignToUser(
        Test $test,
        User $user,
        ?User $assignedBy = null,
        ?Payment $payment = null,
        ?Order $order = null
    ): CandidateTestAssignment {
        // Validate user is eligible as candidate (reject staff accounts)
        if ($user->hasRole(['teacher', 'repository-manager', 'super-admin', 'finance'])) {
            throw new InvalidArgumentException("User '{$user->name}' does not have a candidate role.");
        }

        // For Real Test / Mock Test: Validate published state
        if ($test->isRealTest()) {
            $isPublished = $test->is_published || in_array($test->status, ['published', 'approved']);
            if (!$isPublished || in_array($test->status, ['draft', 'pending', 'pending_approval', 'needs_revision'])) {
                throw new InvalidArgumentException("Cannot assign unpublished Mock Test '{$test->title}'. Assessment must be approved and published first.");
            }
        }

        // For Real Test: Validate paid payment transaction
        if ($test->isRealTest() && !$this->isPaymentEligible($test, $user, $payment, $order)) {
            throw new InvalidArgumentException("Mock Test '{$test->title}' requires a confirmed PAID transaction before candidate assignment.");
        }

        $assignment = CandidateTestAssignment::where('user_id', $user->id)
            ->where('test_id', $test->id)
            ->where('status', 'active')
            ->first();

        if ($assignment) {
            $assignment->update([
                'assigned_by'  => $assignedBy?->id,
                'payment_id'   => $payment?->id,
                'order_id'     => $order?->id,
                'assigned_at'  => now(),
            ]);
        } else {
            $assignment = CandidateTestAssignment::create([
                'user_id'        => $user->id,
                'test_id'        => $test->id,
                'assigned_by'    => $assignedBy?->id,
                'payment_id'     => $payment?->id,
                'order_id'       => $order?->id,
                'status'         => 'active',
                'max_attempts'   => 2,
                'attempts_count' => 0,
                'assigned_at'    => now(),
            ]);
        }

        event(new TestAssigned($assignment));

        return $assignment;
    }

    /**
     * Check if a candidate is eligible to take a test.
     */
    public function isEligibleToStart(Test $test, User $user): bool
    {
        if ($test->isSimulator()) {
            return true;
        }

        return CandidateTestAssignment::where('user_id', $user->id)
            ->where('test_id', $test->id)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Assign test to all enrolled students of a course.
     *
     * @return array<int, CandidateTestAssignment>
     */
    public function assignToCourse(Test $test, string $courseId, ?User $assignedBy = null): array
    {
        $students = User::whereHas('enrollments', function ($q) use ($courseId) {
            $q->where('course_id', $courseId)->where('status', 'active');
        })->get();

        $assignments = [];
        foreach ($students as $student) {
            $assignments[] = $this->assignToUser($test, $student, $assignedBy);
        }

        return $assignments;
    }

    /**
     * Unassign / Revoke test assignment for a candidate.
     */
    public function unassign(Test $test, User $user): bool
    {
        $assignment = CandidateTestAssignment::where('test_id', $test->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if ($assignment) {
            $assignment->update(['status' => 'unassigned']);
            return true;
        }

        return false;
    }

    /**
     * Backward-compatible revoke assignment method.
     */
    public function revokeAssignment(Attempt|CandidateTestAssignment $attempt): Attempt|CandidateTestAssignment
    {
        if ($attempt instanceof CandidateTestAssignment) {
            $attempt->update(['status' => 'unassigned']);
        } else {
            $attempt->update(['status' => 'cancelled']);
        }

        event(new AssignmentRevoked($attempt));

        return $attempt;
    }
}
