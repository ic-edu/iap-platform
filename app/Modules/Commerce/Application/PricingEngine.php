<?php

namespace App\Modules\Commerce\Application;

use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\Product;

class PricingEngine
{
    /**
     * Calculate price breakdown for a product with optional coupon and tax.
     *
     * @return array{base_price: float, discount: float, tax: float, grand_total: float}
     */
    public function calculate(Product $product, int $quantity = 1, ?Coupon $coupon = null, float $taxRatePct = 11.0): array
    {
        $basePrice = (float) $product->price * $quantity;
        $discount = 0.0;

        if ($coupon && $coupon->is_active) {
            if ($coupon->type === 'percentage') {
                $discount = ($basePrice * ($coupon->value / 100));
            } else {
                $discount = min($basePrice, (float) $coupon->value);
            }
        }

        $taxableAmount = max(0.0, $basePrice - $discount);
        $tax = round($taxableAmount * ($taxRatePct / 100), 2);
        $grandTotal = round($taxableAmount + $tax, 2);

        return [
            'base_price' => $basePrice,
            'discount' => round($discount, 2),
            'tax' => $tax,
            'grand_total' => $grandTotal,
        ];
    }
}
