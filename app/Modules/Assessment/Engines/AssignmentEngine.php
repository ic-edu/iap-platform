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
}
