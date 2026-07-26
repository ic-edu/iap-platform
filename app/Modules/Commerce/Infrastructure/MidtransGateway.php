<?php

namespace App\Modules\Commerce\Infrastructure;

use App\Modules\Commerce\Domain\Models\Invoice;

class MidtransGateway implements PaymentGatewayInterface
{
    public function charge(Invoice $invoice): array
    {
        return [
            'success' => true,
            'transaction_id' => 'MID-'.$invoice->invoice_number,
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/example-snap-token',
            'message' => 'Midtrans Payment Snap Token Created.',
        ];
    }

    public function verify(string $transactionId): array
    {
        return ['status' => 'settlement', 'transaction_id' => $transactionId];
    }

    public function refund(string $transactionId, float $amount): array
    {
        return ['success' => true, 'refund_id' => 'MID-RFD-'.$transactionId];
    }

    public function cancel(string $transactionId): array
    {
        return ['success' => true];
    }
}
