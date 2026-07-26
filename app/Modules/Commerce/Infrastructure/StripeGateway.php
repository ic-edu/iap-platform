<?php

namespace App\Modules\Commerce\Infrastructure;

use App\Modules\Commerce\Domain\Models\Invoice;

class StripeGateway implements PaymentGatewayInterface
{
    public function charge(Invoice $invoice): array
    {
        return [
            'success' => true,
            'transaction_id' => 'STP-'.$invoice->invoice_number,
            'redirect_url' => 'https://checkout.stripe.com/pay/example-session-id',
            'message' => 'Stripe Checkout Session Created.',
        ];
    }

    public function verify(string $transactionId): array
    {
        return ['status' => 'succeeded', 'transaction_id' => $transactionId];
    }

    public function refund(string $transactionId, float $amount): array
    {
        return ['success' => true, 'refund_id' => 'STP-RFD-'.$transactionId];
    }

    public function cancel(string $transactionId): array
    {
        return ['success' => true];
    }
}
