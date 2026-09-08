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
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Organization\Enums\EntitlementStatus;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\SeatAllocationStatus;
use App\Modules\Organization\Models\OrganizationSeatAllocation;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
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

        $testFamily = is_object($test->test_type) ? strtolower($test->test_type->value) : strtolower((string) $test->test_type);

        // 1. If explicit $payment is provided, validate it strictly
        if ($payment) {
            $isCandidatePayment = ((int) $payment->user_id === (int) $user->id)
                || ($payment->invoice && (int) $payment->invoice->user_id === (int) $user->id);
            $isPaid = in_array($payment->status, [PaymentStatus::Success, PaymentStatus::Paid], true)
                || in_array(is_object($payment->status) ? $payment->status->value : $payment->status, ['success', 'paid'], true);

            if ($isCandidatePayment && $isPaid) {
                $orderToCheck = $payment->invoice?->order ?? $order;
                if ($orderToCheck) {
                    foreach ($orderToCheck->items as $item) {
                        $product = $item->product;
                        if ($product && $product->is_active) {
                            if ($product->test_id && (string) $product->test_id === (string) $test->id) {
                                return true;
                            }
                            if ($testFamily && $product->getEffectiveFamily() === $testFamily) {
                                return true;
                            }
                        }
                    }
                }
            }
            return false;
        }

        // 2. If explicit $order is provided, validate it strictly
        if ($order) {
            $isCandidateOrder = (int) $order->user_id === (int) $user->id;
            $hasConfirmedPayment = $order->invoice && $order->invoice->payments()
                ->where(function ($q) {
                    $q->whereIn('status', [PaymentStatus::Success, PaymentStatus::Paid])
                      ->orWhereIn('status', ['success', 'paid']);
                })
                ->exists();

            if ($isCandidateOrder && ($hasConfirmedPayment || $order->status === OrderStatus::Completed || $order->status === 'completed')) {
                foreach ($order->items as $item) {
                    $product = $item->product;
                    if ($product && $product->is_active) {
                        if ($product->test_id && (string) $product->test_id === (string) $test->id) {
                            return true;
                        }
                        if ($testFamily && $product->getEffectiveFamily() === $testFamily) {
                            return true;
                        }
                    }
                }
            }
            return false;
        }

        // 3. Fallback: Query database for confirmed payments matching specific test OR assessment family
        return Payment::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhereHas('invoice', fn($inv) => $inv->where('user_id', $user->id));
        })
        ->where(function ($q) {
            $q->whereIn('status', [PaymentStatus::Success, PaymentStatus::Paid])
              ->orWhereIn('status', ['success', 'paid']);
        })
        ->whereHas('invoice.order.items.product', function ($p) use ($test, $testFamily) {
            $p->where('is_active', true)
              ->where(function ($match) use ($test, $testFamily) {
                  $match->where('test_id', $test->id);
                  if ($testFamily) {
                      $match->orWhere(function ($pkg) use ($testFamily) {
                          $pkg->where('assessment_family', $testFamily)
                              ->orWhereHas('test', fn($t) => $t->where('test_type', $testFamily));
                      });
                  }
              });
        })
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
            return collect();
        }

        $testFamily = is_object($test->test_type) ? strtolower($test->test_type->value) : strtolower((string) $test->test_type);

        return User::role('student')
            ->whereHas('orders', function ($q) use ($test, $testFamily) {
                $q->whereHas('invoice.payments', function ($pm) {
                    $pm->whereIn('status', [PaymentStatus::Success, PaymentStatus::Paid])
                       ->orWhereIn('status', ['success', 'paid']);
                })
                ->whereHas('items.product', function ($p) use ($test, $testFamily) {
                    $p->where('is_active', true)
                      ->where(function ($match) use ($test, $testFamily) {
                          $match->where('test_id', $test->id);
                          if ($testFamily) {
                              $match->orWhere(function ($pkg) use ($testFamily) {
                                  $pkg->where('assessment_family', $testFamily)
                                      ->orWhereHas('test', fn($t) => $t->where('test_type', $testFamily));
                              });
                          }
                      });
                });
            })
            ->get();
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

        // Validate canonical published state
        if (!$test->isPublished()) {
            $label = $test->isRealTest() ? 'Mock Test' : 'Assessment';
            throw new InvalidArgumentException("Cannot assign unpublished {$label} '{$test->title}'. {$label} must be published first.");
        }

        // Simulator / Practice mode uses open candidate access and does not support manual candidate assignment
        if ($test->isSimulator()) {
            throw new InvalidArgumentException("Test Simulator '{$test->title}' uses open candidate access and does not support manual candidate assignment.");
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
        if (!$test->isPublished()) {
            return false;
        }

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

    /**
     * Assign a published test to a candidate holding an active institutional seat allocation (RA action).
     *
     * @throws InvalidArgumentException
     */
    public function assignFromOrganizationSeat(
        OrganizationSeatAllocation $allocation,
        Test $test,
        User $assignedBy
    ): CandidateTestAssignment {
        // 1. Authorize actor: Routine institutional assignment must be performed by Operational Admin / RA
        if (!$assignedBy->hasRole(['admin', 'super-admin'])) {
            throw new InvalidArgumentException("Unauthorized: Only Operational Admin / RA can assign institutional assessments.");
        }

        // 2. Candidate resolution & role validation
        $candidate = $allocation->membership?->user;
        if (!$candidate) {
            throw new InvalidArgumentException("Invalid candidate user for seat allocation.");
        }

        if (!$candidate->hasRole('student') || $candidate->hasRole(['teacher', 'repository-manager', 'super-admin', 'finance', 'organization-coordinator'])) {
            throw new InvalidArgumentException("User '{$candidate->name}' does not have candidate/student role.");
        }

        // 3. Seat allocation, membership, and entitlement lifecycle status validation
        if ($allocation->status !== SeatAllocationStatus::Active) {
            $statusVal = is_object($allocation->status) ? $allocation->status->value : $allocation->status;
            throw new InvalidArgumentException("Cannot assign assessment. Seat allocation is {$statusVal}.");
        }

        if ($allocation->membership?->status !== MembershipStatus::Active) {
            $mStatus = is_object($allocation->membership?->status) ? $allocation->membership->status->value : $allocation->membership?->status;
            throw new InvalidArgumentException("Cannot assign assessment. Candidate membership is {$mStatus}.");
        }

        if ($allocation->entitlement?->status !== EntitlementStatus::Active) {
            $eStatus = is_object($allocation->entitlement?->status) ? $allocation->entitlement->status->value : $allocation->entitlement?->status;
            throw new InvalidArgumentException("Cannot assign assessment. Organization entitlement is {$eStatus}.");
        }

        // 4. Tenant boundary validation
        if ((string) $allocation->membership?->organization_id !== (string) $allocation->entitlement?->organization_id) {
            throw new InvalidArgumentException("Cross-organization violation: Candidate does not belong to the entitlement organization.");
        }

        // 5. Test publication and mode gating
        if (!$test->isPublished()) {
            $label = $test->isRealTest() ? 'Mock Test' : 'Assessment';
            throw new InvalidArgumentException("Cannot assign unpublished {$label} '{$test->title}'. {$label} must be published first.");
        }

        if ($test->isSimulator()) {
            throw new InvalidArgumentException("Test Simulator '{$test->title}' uses open candidate access and does not support manual candidate assignment.");
        }

        // 6. Product & Family compatibility validation
        $product = $allocation->entitlement?->product;
        if (!$product) {
            throw new InvalidArgumentException("No product linked to this entitlement.");
        }

        if ($product->test_id && (string) $product->test_id !== (string) $test->id) {
            throw new InvalidArgumentException("Assessment '{$test->title}' does not match the product specific test requirement.");
        }

        $testFamily = is_object($test->test_type) ? strtolower($test->test_type->value) : strtolower((string) $test->test_type);
        $productFamily = $product->getEffectiveFamily();
        if (!$product->test_id && $productFamily && $testFamily !== $productFamily) {
            throw new InvalidArgumentException("Incompatible Assessment: Package family '{$productFamily}' does not match test family '{$testFamily}'.");
        }

        // 7. Atomic assignment creation with idempotency and lock protection
        return DB::transaction(function () use ($allocation, $test, $candidate, $assignedBy) {
            $lockedAlloc = OrganizationSeatAllocation::where('id', $allocation->id)->lockForUpdate()->firstOrFail();

            if ($lockedAlloc->status !== SeatAllocationStatus::Active) {
                throw new InvalidArgumentException("Cannot assign assessment. Seat allocation is {$lockedAlloc->status->value}.");
            }

            $sourceOrder = $lockedAlloc->entitlement?->orderItem?->order ?? $lockedAlloc->entitlement?->order;
            $sourcePayment = $sourceOrder?->invoice?->payments()
                ->where(function ($q) {
                    $q->whereIn('status', [PaymentStatus::Success, PaymentStatus::Paid])
                      ->orWhereIn('status', ['success', 'paid']);
                })
                ->first();

            // Check if active assignment already exists for this seat allocation
            $existingSeatAssignment = CandidateTestAssignment::where('organization_seat_allocation_id', $lockedAlloc->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($existingSeatAssignment) {
                throw new InvalidArgumentException("Active assessment assignment already exists for this institutional seat allocation.");
            }

            $assignment = CandidateTestAssignment::where('user_id', $candidate->id)
                ->where('test_id', $test->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($assignment) {
                $assignment->update([
                    'assigned_by'                     => $assignedBy->id,
                    'payment_id'                      => $sourcePayment?->id ?? $assignment->payment_id,
                    'order_id'                        => $sourceOrder?->id ?? $assignment->order_id,
                    'organization_seat_allocation_id' => $lockedAlloc->id,
                    'assigned_at'                     => now(),
                ]);
            } else {
                $assignment = CandidateTestAssignment::create([
                    'user_id'                         => $candidate->id,
                    'test_id'                         => $test->id,
                    'assigned_by'                     => $assignedBy->id,
                    'payment_id'                      => $sourcePayment?->id,
                    'order_id'                        => $sourceOrder?->id,
                    'organization_seat_allocation_id' => $lockedAlloc->id,
                    'status'                          => 'active',
                    'max_attempts'                    => 2,
                    'attempts_count'                  => 0,
                    'assigned_at'                     => now(),
                ]);
            }

            event(new TestAssigned($assignment));

            ActivityLogger::log(
                action: 'CANDIDATE_ASSIGNED',
                description: "Assigned candidate '{$candidate->name}' to test '{$test->title}' from institutional seat",
                subject: $assignment,
                properties: [
                    'organization_id'                 => $lockedAlloc->entitlement?->organization_id,
                    'organization_seat_allocation_id' => $lockedAlloc->id,
                    'order_id'                        => $sourceOrder?->id,
                    'candidate_id'                    => $candidate->id,
                    'test_id'                         => $test->id,
                    'assigned_by'                     => $assignedBy->id,
                ]
            );

            return $assignment;
        });
    }

    /**
     * Get all eligible published real tests matching a package's family or test_id.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Test>
     */
    public function getEligibleTestsForPackage(Product $product)
    {
        if ($product->test_id) {
            return Test::published()
                ->where('id', $product->test_id)
                ->where(function ($q) {
                    $q->where('assessment_mode', 'real_test')
                      ->orWhere('assessment_mode', '!=', 'simulator');
                })
                ->get();
        }

        $family = $product->getEffectiveFamily();
        if (!$family) {
            return collect();
        }

        return Test::published()
            ->where(function ($q) use ($family) {
                $q->where('test_type', $family)
                  ->orWhere('test_type', strtoupper($family));
            })
            ->where(function ($q) {
                $q->where('assessment_mode', 'real_test')
                  ->orWhere('assessment_mode', '!=', 'simulator');
            })
            ->get();
    }
}
