<?php

namespace App\Modules\Commerce\Events;

use App\Modules\Commerce\Domain\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Invoice $invoice) {}
}
