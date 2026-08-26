<?php

namespace App\Modules\Commerce\Application;

use App\Modules\Commerce\Domain\Enums\InvoiceStatus;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Events\PaymentCancelled;
use App\Modules\Commerce\Events\PaymentConfirmed;
use App\Modules\Commerce\Events\PaymentCreated;
use App\Modules\Commerce\Events\PaymentRefunded;
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
            'invoice_id' => $invoice->id,
            'user_id' => $invoice->user_id,
            'reference_number' => $refNumber,
            'payment_gateway' => $gateway,
            'status' => PaymentStatus::Pending,
            'amount' => $invoice->amount,
        ]);

        event(new PaymentCreated($payment));

        return $payment;
    }

    /**
     * Confirm payment and trigger automatic enrollment activation.
     */
    public function confirmPayment(Payment $payment, ?string $transactionId = null): Payment
    {
        $payment->update([
            'status' => PaymentStatus::Success,
            'transaction_id' => $transactionId ?? 'TXN-'.now()->timestamp,
            'confirmed_at' => now(),
        ]);

        $invoice = $payment->invoice;
        if ($invoice) {
            $invoice->update([
                'status' => InvoiceStatus::Paid,
                'paid_at' => now(),
            ]);

            $order = $invoice->order;
            if ($order) {
                $order->update(['status' => OrderStatus::Completed]);
            }
        }

        event(new PaymentConfirmed($payment));

        return $payment;
    }

    /**
     * Cancel payment.
     */
    public function cancelPayment(Payment $payment): Payment
    {
        $payment->update(['status' => PaymentStatus::Failed]);

        if ($payment->invoice) {
            $payment->invoice->update(['status' => InvoiceStatus::Cancelled]);
            if ($payment->invoice->order) {
                $payment->invoice->order->update(['status' => OrderStatus::Cancelled]);
            }
        }

        event(new PaymentCancelled($payment));

        return $payment;
    }

    /**
     * Refund payment.
     */
    public function refundPayment(Payment $payment): Payment
    {
        $payment->update(['status' => PaymentStatus::Refunded]);

        if ($payment->invoice) {
            $payment->invoice->update(['status' => InvoiceStatus::Cancelled]);
            if ($payment->invoice->order) {
                $payment->invoice->order->update(['status' => OrderStatus::Refunded]);
            }
        }

        event(new PaymentRefunded($payment));

        return $payment;
    }
}
