<?php

namespace App\Integrations;

class PaymentIntegrationDriver implements PaymentIntegrationInterface
{
    public function charge(string $invoiceId, float $amount): array
    {
        return [
            'transaction_id' => 'PAY-INT-'.now()->timestamp,
            'status' => 'success',
        ];
    }
}
