<?php

namespace App\Modules\Commerce\Infrastructure;

use App\Modules\Commerce\Domain\Models\Invoice;

class ManualTransferGateway implements PaymentGatewayInterface
{
    public function charge(Invoice $invoice): array
    {
        return [
            'success' => true,
            'transaction_id' => 'MAN-'.$invoice->invoice_number,
            'redirect_url' => null,
            'message' => 'Please transfer to Bank Account BCA 123-456-7890.',
        ];
    }

    public function verify(string $transactionId): array
    {
        return ['status' => 'success', 'transaction_id' => $transactionId];
    }

    public function refund(string $transactionId, float $amount): array
    {
        return ['success' => true, 'refund_id' => 'RFD-'.$transactionId];
    }

    public function cancel(string $transactionId): array
    {
        return ['success' => true];
    }
}
