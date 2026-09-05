<?php

namespace App\Modules\Commerce\Application;

use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Events\CouponApplied;

class CouponEngine
{
    /**
     * Validate coupon/voucher code for candidate use.
     *
     * @return array{valid: bool, coupon: Coupon|null, reason: string|null}
     */
    public function validateCoupon(string $code): array
    {
        $normalizedCode = strtoupper(trim($code));

        $coupon = Coupon::where('code', $normalizedCode)->first();

        if (!$coupon) {
            // Check if it was soft-deleted
            $trashed = Coupon::onlyTrashed()->where('code', $normalizedCode)->first();
            if ($trashed) {
                return ['valid' => false, 'coupon' => null, 'reason' => 'This voucher is no longer available.'];
            }

            return ['valid' => false, 'coupon' => null, 'reason' => 'Invalid voucher code.'];
        }

        if (!$coupon->is_active) {
            return ['valid' => false, 'coupon' => $coupon, 'reason' => 'This voucher is inactive.'];
        }

        if ($coupon->valid_until === null) {
            return ['valid' => false, 'coupon' => $coupon, 'reason' => 'This voucher requires validity configuration before use.'];
        }

        $now = now();

        if ($coupon->valid_from !== null && $now->lt($coupon->valid_from)) {
            return ['valid' => false, 'coupon' => $coupon, 'reason' => 'This voucher is not active yet.'];
        }

        if ($now->gt($coupon->valid_until)) {
            return ['valid' => false, 'coupon' => $coupon, 'reason' => 'This voucher has expired.'];
        }

        if ($coupon->used_count >= $coupon->usage_limit) {
            return ['valid' => false, 'coupon' => $coupon, 'reason' => 'Voucher usage limit reached.'];
        }

        event(new CouponApplied($coupon));

        return ['valid' => true, 'coupon' => $coupon, 'reason' => null];
    }
}
