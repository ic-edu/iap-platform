<?php

namespace App\Modules\Commerce\Events;

use App\Modules\Commerce\Domain\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentRefunded
{
    use Dispatchable, SerializesModels;

    public function __construct(public Payment $payment) {}
}
