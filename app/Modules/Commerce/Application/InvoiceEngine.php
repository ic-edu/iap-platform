<?php

namespace App\Modules\Commerce\Application;

use App\Modules\Commerce\Domain\Enums\InvoiceStatus;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Events\InvoiceGenerated;
use Illuminate\Support\Str;

class InvoiceEngine
{
    /**
     * Generate official invoice for an order.
     */
    public function generateInvoice(Order $order): Invoice
    {
        $dateStr = now()->format('Ymd');
        $randomCode = strtoupper(Str::random(4));
        $invoiceNumber = "INV-{$dateStr}-{$randomCode}";

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'invoice_number' => $invoiceNumber,
            'status' => InvoiceStatus::Unpaid,
            'amount' => $order->grand_total,
            'due_date' => now()->addHours(24),
        ]);

        event(new InvoiceGenerated($invoice));

        return $invoice;
    }
}
