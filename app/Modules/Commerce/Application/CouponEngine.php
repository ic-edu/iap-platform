<?php

namespace App\Modules\Commerce\Application;

use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Events\CouponApplied;

class CouponEngine
{
    /**
     * Validate coupon code for use.
     *
     * @return array{valid: bool, coupon: Coupon|null, reason: string|null}
     */
    public function validateCoupon(string $code): array
    {
        $coupon = Coupon::where('code', strtoupper($code))->first();

        if (!$coupon) {
            return ['valid' => false, 'coupon' => null, 'reason' => 'Invalid coupon code.'];
        }

        if (!$coupon->is_active) {
            return ['valid' => false, 'coupon' => $coupon, 'reason' => 'Coupon is inactive.'];
        }

        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            return ['valid' => false, 'coupon' => $coupon, 'reason' => 'Coupon has expired.'];
        }

        if ($coupon->used_count >= $coupon->usage_limit) {
            return ['valid' => false, 'coupon' => $coupon, 'reason' => 'Coupon usage limit reached.'];
        }

        event(new CouponApplied($coupon));

        return ['valid' => true, 'coupon' => $coupon, 'reason' => null];
    }
}
