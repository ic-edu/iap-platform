<?php

namespace App\Modules\Commerce\Application;

use App\Models\User;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Commerce\Events\CouponApplied;
use App\Modules\Organization\Models\Organization;

class CouponEngine
{
    /**
     * Validate coupon/voucher code with optional product applicability.
     *
     * @return array{valid: bool, coupon: Coupon|null, reason: string|null}
     */
    public function validateCoupon(
        string $code,
        ?Product $product = null,
        ?User $user = null,
        ?Organization $organization = null
    ): array {
        $normalizedCode = strtoupper(trim($code));

        if (empty($normalizedCode)) {
            return ['valid' => false, 'coupon' => null, 'reason' => 'Please provide a valid voucher code.'];
        }

        $coupon = Coupon::with(['campaign.products'])->where('code', $normalizedCode)->first();

        if (!$coupon) {
            // Check if it was soft-deleted
            $trashed = Coupon::onlyTrashed()->where('code', $normalizedCode)->first();
            if ($trashed) {
                return ['valid' => false, 'coupon' => null, 'reason' => 'This voucher is no longer available.'];
            }

            return ['valid' => false, 'coupon' => null, 'reason' => 'Invalid voucher code.'];
        }

        // 1. Parent Campaign Checks
        if ($coupon->campaign_id && $coupon->campaign) {
            $campaign = $coupon->campaign;

            if ($campaign->trashed() || !$campaign->is_active) {
                return ['valid' => false, 'coupon' => $coupon, 'reason' => 'This voucher campaign is inactive.'];
            }

            if ($campaign->valid_until === null) {
                return ['valid' => false, 'coupon' => $coupon, 'reason' => 'This voucher requires validity configuration before use.'];
            }

            $now = now();

            if ($campaign->valid_from !== null && $now->lt($campaign->valid_from)) {
                return ['valid' => false, 'coupon' => $coupon, 'reason' => 'This voucher is not active yet.'];
            }

            if ($now->gt($campaign->valid_until)) {
                return ['valid' => false, 'coupon' => $coupon, 'reason' => 'This voucher has expired.'];
            }
        }

        // 2. Individual Coupon Status Checks
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

        // 3. Usage Capacity Check (incorporates consumed + active reservations)
        if ($coupon->getAvailableUses() <= 0) {
            return ['valid' => false, 'coupon' => $coupon, 'reason' => 'Voucher usage limit reached.'];
        }

        // 4. Product & Assessment Family Applicability Check
        if ($product && $coupon->campaign_id && $coupon->campaign) {
            $campaign = $coupon->campaign;
            $productFamily = $product->getEffectiveFamily();

            if (empty($productFamily) || strtolower($productFamily) !== strtolower($campaign->assessment_family)) {
                $familyLabel = strtoupper($campaign->assessment_family);
                return [
                    'valid'  => false,
                    'coupon' => $coupon,
                    'reason' => "This voucher is only applicable to {$familyLabel} packages.",
                ];
            }

            if (!$campaign->isProductEligible($product)) {
                return [
                    'valid'  => false,
                    'coupon' => $coupon,
                    'reason' => 'This voucher is not applicable to the selected package.',
                ];
            }
        }

        event(new CouponApplied($coupon));

        return ['valid' => true, 'coupon' => $coupon, 'reason' => null];
    }
}
