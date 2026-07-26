<?php

namespace App\Modules\Commerce\Infrastructure;

use App\Modules\Commerce\Domain\Models\Invoice;

interface PaymentGatewayInterface
{
    /**
     * Initiate payment charge request.
     *
     * @return array{success: bool, transaction_id: string, redirect_url: string|null, message: string}
     */
    public function charge(Invoice $invoice): array;

    /**
     * Verify payment status.
     *
     * @return array{status: string, transaction_id: string}
     */
    public function verify(string $transactionId): array;

    /**
     * Issue refund for transaction.
     *
     * @return array{success: bool, refund_id: string}
     */
    public function refund(string $transactionId, float $amount): array;

    /**
     * Cancel pending transaction.
     *
     * @return array{success: bool}
     */
    public function cancel(string $transactionId): array;
}
