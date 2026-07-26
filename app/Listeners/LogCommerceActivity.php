<?php

namespace App\Listeners;

use App\Modules\Commerce\Events\CheckoutCompleted;
use App\Modules\Commerce\Events\InvoiceGenerated;
use App\Modules\Commerce\Events\PaymentCancelled;
use App\Modules\Commerce\Events\PaymentConfirmed;
use App\Modules\Commerce\Events\PaymentCreated;
use App\Modules\Commerce\Events\PaymentRefunded;
use App\Services\ActivityLogger;

class LogCommerceActivity
{
    public function __construct(protected ActivityLogger $logger) {}

    public function handleCheckoutCompleted(CheckoutCompleted $event): void
    {
        $this->logger->log(
            'checkout_completed',
            "Checkout completed for Order {$event->order->order_number} (Invoice {$event->invoice->invoice_number})",
            $event->order,
            null,
            $event->order->user_id
        );
    }

    public function handleInvoiceGenerated(InvoiceGenerated $event): void
    {
        $this->logger->log(
            'invoice_generated',
            "Invoice {$event->invoice->invoice_number} generated for amount {$event->invoice->amount}",
            $event->invoice,
            null,
            $event->invoice->user_id
        );
    }

    public function handlePaymentCreated(PaymentCreated $event): void
    {
        $this->logger->log(
            'payment_created',
            "Payment request created for Invoice {$event->payment->invoice_id}",
            $event->payment,
            null,
            $event->payment->user_id
        );
    }

    public function handlePaymentConfirmed(PaymentConfirmed $event): void
    {
        $this->logger->log(
            'payment_confirmed',
            "Payment confirmed for transaction {$event->payment->transaction_id}",
            $event->payment,
            null,
            $event->payment->user_id
        );
    }

    public function handlePaymentCancelled(PaymentCancelled $event): void
    {
        $this->logger->log(
            'payment_cancelled',
            "Payment cancelled for Invoice {$event->payment->invoice_id}",
            $event->payment,
            null,
            $event->payment->user_id
        );
    }

    public function handlePaymentRefunded(PaymentRefunded $event): void
    {
        $this->logger->log(
            'payment_refunded',
            "Payment refunded for transaction {$event->payment->transaction_id}",
            $event->payment,
            null,
            $event->payment->user_id
        );
    }
}
