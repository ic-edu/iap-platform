<?php

namespace App\Modules\Commerce\Infrastructure;

use App\Modules\Commerce\Domain\Models\Invoice;

class XenditGateway implements PaymentGatewayInterface
{
    public function charge(Invoice $invoice): array
    {
        return [
            'success' => true,
            'transaction_id' => 'XND-'.$invoice->invoice_number,
            'redirect_url' => 'https://checkout.xendit.co/web/example-invoice-id',
            'message' => 'Xendit Invoice URL Generated.',
        ];
    }

    public function verify(string $transactionId): array
    {
        return ['status' => 'PAID', 'transaction_id' => $transactionId];
    }

    public function refund(string $transactionId, float $amount): array
    {
        return ['success' => true, 'refund_id' => 'XND-RFD-'.$transactionId];
    }

    public function cancel(string $transactionId): array
    {
        return ['success' => true];
    }
}
