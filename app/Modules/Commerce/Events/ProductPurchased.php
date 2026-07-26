<?php

namespace App\Modules\Commerce\Events;

use App\Modules\Commerce\Domain\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductPurchased
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order) {}
}
