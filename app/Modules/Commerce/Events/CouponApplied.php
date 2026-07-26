<?php

namespace App\Modules\Commerce\Events;

use App\Modules\Commerce\Domain\Models\Coupon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CouponApplied
{
    use Dispatchable, SerializesModels;

    public function __construct(public Coupon $coupon) {}
}
