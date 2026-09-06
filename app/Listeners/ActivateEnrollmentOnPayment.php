<?php

namespace App\Listeners;

use App\Modules\Academic\Engines\EnrollmentEngine;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Commerce\Events\PaymentConfirmed;
use App\Services\NotificationService;

class ActivateEnrollmentOnPayment
{
    public function __construct(
        protected EnrollmentEngine $enrollmentEngine,
        protected AssignmentEngine $assignmentEngine,
        protected NotificationService $notificationService
    ) {}

    /**
     * Handle payment confirmed event to activate course/test access.
     */
    public function handle(PaymentConfirmed $event): void
    {
        $payment = $event->payment;
        $invoice = $payment->invoice;
        if (!$invoice) {
            return;
        }

        $order = $invoice->order;
        if (!$order) {
            return;
        }

        // Institutional orders provision OrganizationEntitlements instead of individual enrollment
        if ($order->organization_id) {
            return;
        }

        $user = $order->user;
        if (!$user) {
            return;
        }

        foreach ($order->items as $item) {
            $product = $item->product;
            if (!$product) {
                continue;
            }

            if ($product->course) {
                $this->enrollmentEngine->enrollStudent($product->course, $user);
            }

            if ($product->test) {
                // Simulators: Open candidate access by default; no CandidateTestAssignment required.
                // Real Tests: Candidate becomes eligible (PAID), awaiting explicit Admin assignment.
            }
        }

        $this->notificationService->send($user, 'Payment Successful', "Your payment for Invoice {$invoice->invoice_number} was confirmed.");
    }
}
