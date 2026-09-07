<?php

namespace App\Modules\Commerce\Application;

use App\Modules\Commerce\Domain\Enums\InvoiceStatus;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\CouponRedemption;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Events\PaymentCancelled;
use App\Modules\Commerce\Events\PaymentConfirmed;
use App\Modules\Commerce\Events\PaymentCreated;
use App\Modules\Commerce\Events\PaymentRefunded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingEngine
{
    /**
     * Create payment entry for an invoice.
     */
    public function createPayment(Invoice $invoice, string $gateway = 'manual_transfer'): Payment
    {
        $existing = Payment::where('invoice_id', $invoice->id)
            ->where('status', PaymentStatus::Pending)
            ->first();

        if ($existing) {
            return $existing;
        }

        $refNumber = 'PAY-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));

        $payment = Payment::create([
            'invoice_id'       => $invoice->id,
            'user_id'          => $invoice->user_id,
            'reference_number' => $refNumber,
            'payment_gateway'  => $gateway,
            'status'           => PaymentStatus::Pending,
            'amount'           => $invoice->amount,
        ]);

        event(new PaymentCreated($payment));

        return $payment;
    }

    /**
     * Confirm payment, consume voucher redemption, and trigger automatic enrollment/entitlement activation.
     */
    public function confirmPayment(Payment $payment, ?string $transactionId = null): Payment
    {
        // Idempotency: If already confirmed, return current state without re-dispatching events
        if ($payment->status === PaymentStatus::Success) {
            return $payment;
        }

        if ($payment->status !== PaymentStatus::Pending) {
            $statusVal = is_object($payment->status) ? $payment->status->value : $payment->status;
            throw new \InvalidArgumentException("Cannot confirm payment. Only pending payments can be approved. Current status: {$statusVal}");
        }

        return DB::transaction(function () use ($payment, $transactionId) {
            $payment->update([
                'status'         => PaymentStatus::Success,
                'transaction_id' => $transactionId ?? 'TXN-'.now()->timestamp,
                'confirmed_at'   => now(),
            ]);

            $invoice = $payment->invoice;
            if ($invoice) {
                $invoice->update([
                    'status'  => InvoiceStatus::Paid,
                    'paid_at' => now(),
                ]);

                $order = $invoice->order;
                if ($order) {
                    $order->update(['status' => OrderStatus::Completed]);

                    // Consume voucher redemption if reserved
                    $redemption = CouponRedemption::where('order_id', $order->id)->lockForUpdate()->first();
                    if ($redemption && $redemption->status === 'reserved') {
                        $redemption->update([
                            'status'      => 'consumed',
                            'consumed_at' => now(),
                        ]);

                        if ($redemption->coupon) {
                            $redemption->coupon->increment('used_count');
                        }
                    }
                }
            }

            event(new PaymentConfirmed($payment));

            return $payment;
        });
    }

    /**
     * Cancel payment and release reserved voucher.
     */
    public function cancelPayment(Payment $payment, ?string $reason = null): Payment
    {
        if ($payment->status === PaymentStatus::Failed) {
            return $payment;
        }

        if ($payment->status !== PaymentStatus::Pending) {
            $statusVal = is_object($payment->status) ? $payment->status->value : $payment->status;
            throw new \InvalidArgumentException("Cannot cancel payment. Only pending payments can be cancelled. Current status: {$statusVal}");
        }

        return DB::transaction(function () use ($payment, $reason) {
            $payment->update([
                'status'      => PaymentStatus::Failed,
                'proof_notes' => $reason ? ($payment->proof_notes ? $payment->proof_notes." | Rejection reason: {$reason}" : "Rejection reason: {$reason}") : $payment->proof_notes,
            ]);

            if ($payment->invoice) {
                $payment->invoice->update(['status' => InvoiceStatus::Cancelled]);
                $order = $payment->invoice->order;
                if ($order) {
                    $order->update(['status' => OrderStatus::Cancelled]);

                    // Release reserved voucher redemption
                    $redemption = CouponRedemption::where('order_id', $order->id)->lockForUpdate()->first();
                    if ($redemption && $redemption->status === 'reserved') {
                        $redemption->update([
                            'status'          => 'released',
                            'released_at'     => now(),
                            'released_reason' => $reason ?? 'Payment cancelled or rejected by finance.',
                        ]);
                    }
                }
            }

            event(new PaymentCancelled($payment));

            return $payment;
        });
    }

    /**
     * Refund payment.
     */
    public function refundPayment(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $payment->update(['status' => PaymentStatus::Refunded]);

            if ($payment->invoice) {
                $payment->invoice->update(['status' => InvoiceStatus::Cancelled]);
                $order = $payment->invoice->order;
                if ($order) {
                    $order->update(['status' => OrderStatus::Refunded]);

                    // Release voucher redemption if was consumed
                    $redemption = CouponRedemption::where('order_id', $order->id)->lockForUpdate()->first();
                    if ($redemption && $redemption->status === 'consumed') {
                        $redemption->update([
                            'status'          => 'released',
                            'released_at'     => now(),
                            'released_reason' => 'Payment refunded.',
                        ]);

                        if ($redemption->coupon && $redemption->coupon->used_count > 0) {
                            $redemption->coupon->decrement('used_count');
                        }
                    }
                }
            }

            event(new PaymentRefunded($payment));

            return $payment;
        });
    }
}
