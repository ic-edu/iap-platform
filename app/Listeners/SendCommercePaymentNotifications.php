<?php

namespace App\Listeners;

use App\Models\User;
use App\Modules\Commerce\Events\PaymentCancelled;
use App\Modules\Commerce\Events\PaymentConfirmed;
use App\Modules\Commerce\Events\PaymentCreated;
use App\Notifications\EnterpriseSystemNotification;
use Illuminate\Support\Facades\Log;

class SendCommercePaymentNotifications
{
    /**
     * Handle PaymentCreated event: Notify Finance Officer(s) that a new candidate payment requires review.
     */
    public function handlePaymentCreated(PaymentCreated $event): void
    {
        try {
            $payment = $event->payment->loadMissing(['user', 'invoice.order.items.product']);
            $candidateName = $payment->user?->name ?? 'Candidate';
            $candidateEmail = $payment->user?->email ?? '';
            $packageTitle = $this->resolvePackageTitle($payment);
            $formattedAmount = number_format((float) $payment->amount);

            $financeOfficers = User::role('finance')->get();

            foreach ($financeOfficers as $financeUser) {
                if ($this->hasExistingNotification($financeUser, 'Payment', (string) $payment->id, 'PAYMENT_PENDING')) {
                    continue;
                }

                $financeUser->notify(new EnterpriseSystemNotification(
                    title: '💳 New Candidate Payment Pending Review',
                    message: "Candidate {$candidateName} ({$candidateEmail}) submitted payment #{$payment->reference_number} for {$packageTitle} (IDR {$formattedAmount}). Verification required.",
                    type: 'PAYMENT_PENDING',
                    priority: 'HIGH',
                    entityType: 'Payment',
                    entityId: (string) $payment->id,
                    targetUrl: route('finance.payments.show', $payment->id)
                ));
            }
        } catch (\Throwable $e) {
            Log::error('SendCommercePaymentNotifications@handlePaymentCreated failed: ' . $e->getMessage(), [
                'payment_id' => $event->payment->id ?? null,
                'exception'  => $e,
            ]);
        }
    }

    /**
     * Handle PaymentConfirmed event: Notify Candidate of confirmation and Operational Admin (RA) of eligibility.
     */
    public function handlePaymentConfirmed(PaymentConfirmed $event): void
    {
        try {
            $payment = $event->payment->loadMissing(['user', 'invoice.order.items.product']);
            $candidate = $payment->user;
            $candidateName = $candidate?->name ?? 'Candidate';
            $packageTitle = $this->resolvePackageTitle($payment);
            $formattedAmount = number_format((float) $payment->amount);

            // 1. Notify Candidate Owner
            if ($candidate && !$this->hasExistingNotification($candidate, 'Payment', (string) $payment->id, 'PAYMENT_CONFIRMED')) {
                $candidate->notify(new EnterpriseSystemNotification(
                    title: '🎉 Payment Confirmed',
                    message: "Your payment #{$payment->reference_number} for {$packageTitle} (IDR {$formattedAmount}) has been verified and confirmed.",
                    type: 'PAYMENT_CONFIRMED',
                    priority: 'HIGH',
                    entityType: 'Payment',
                    entityId: (string) $payment->id,
                    targetUrl: route('candidate.payments.show', $payment->id)
                ));
            }

            // 2. Notify Operational Admins (RA)
            $operationalAdmins = User::role('admin')->get();

            foreach ($operationalAdmins as $adminUser) {
                if ($this->hasExistingNotification($adminUser, 'Payment', (string) $payment->id, 'PAYMENT_CONFIRMED')) {
                    continue;
                }

                $adminUser->notify(new EnterpriseSystemNotification(
                    title: '✅ Candidate Payment Confirmed — Paid & Eligible',
                    message: "Candidate {$candidateName} has paid for {$packageTitle} (#{$payment->reference_number}) and is now Paid & Eligible.",
                    type: 'PAYMENT_CONFIRMED',
                    priority: 'HIGH',
                    entityType: 'Payment',
                    entityId: (string) $payment->id,
                    targetUrl: route('admin.dashboard')
                ));
            }
        } catch (\Throwable $e) {
            Log::error('SendCommercePaymentNotifications@handlePaymentConfirmed failed: ' . $e->getMessage(), [
                'payment_id' => $event->payment->id ?? null,
                'exception'  => $e,
            ]);
        }
    }

    /**
     * Handle PaymentCancelled event: Notify Candidate that payment was rejected/cancelled.
     */
    public function handlePaymentCancelled(PaymentCancelled $event): void
    {
        try {
            $payment = $event->payment->loadMissing(['user', 'invoice.order.items.product']);
            $candidate = $payment->user;
            $packageTitle = $this->resolvePackageTitle($payment);

            if ($candidate && !$this->hasExistingNotification($candidate, 'Payment', (string) $payment->id, 'PAYMENT_CANCELLED')) {
                $reason = null;
                if ($payment->proof_notes && str_contains($payment->proof_notes, 'Rejection reason:')) {
                    $parts = explode('Rejection reason:', $payment->proof_notes);
                    $reason = trim(end($parts));
                }

                $message = "Your payment #{$payment->reference_number} for {$packageTitle} was not approved."
                    . ($reason ? " Reason: {$reason}" : " Please review your payment details or contact support.");

                $candidate->notify(new EnterpriseSystemNotification(
                    title: '⚠️ Payment Not Approved',
                    message: $message,
                    type: 'PAYMENT_CANCELLED',
                    priority: 'HIGH',
                    entityType: 'Payment',
                    entityId: (string) $payment->id,
                    targetUrl: route('candidate.payments.show', $payment->id)
                ));
            }
        } catch (\Throwable $e) {
            Log::error('SendCommercePaymentNotifications@handlePaymentCancelled failed: ' . $e->getMessage(), [
                'payment_id' => $event->payment->id ?? null,
                'exception'  => $e,
            ]);
        }
    }

    /**
     * Resolve package title from payment's associated order items.
     */
    protected function resolvePackageTitle($payment): string
    {
        $items = $payment->invoice?->order?->items;
        if ($items && $items->isNotEmpty()) {
            return $items->first()->product?->title ?? 'Assessment Package';
        }
        return 'Assessment Package';
    }

    /**
     * Deterministic duplicate prevention checking existing notifications for this user/entity/type.
     */
    protected function hasExistingNotification(?User $user, string $entityType, string $entityId, string $notificationType): bool
    {
        if (!$user) {
            return false;
        }

        return $user->notifications()
            ->get()
            ->contains(function ($n) use ($entityType, $entityId, $notificationType) {
                $data = is_array($n->data) ? $n->data : (json_decode($n->data ?? '[]', true) ?? []);
                return ($data['entity_type'] ?? null) === $entityType
                    && (string)($data['entity_id'] ?? '') === (string)$entityId
                    && ($data['notification_type'] ?? null) === $notificationType;
            });
    }
}
