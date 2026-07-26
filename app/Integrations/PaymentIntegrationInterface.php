<?php

namespace App\Integrations;

interface PaymentIntegrationInterface
{
    /**
     * Charge payment gateway.
     *
     * @return array{transaction_id: string, status: string}
     */
    public function charge(string $invoiceId, float $amount): array;
}
