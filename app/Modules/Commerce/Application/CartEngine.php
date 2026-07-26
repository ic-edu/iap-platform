<?php

namespace App\Modules\Commerce\Application;

use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\Product;

class CartEngine
{
    /**
     * @var array<string, array{product: Product, quantity: int}>
     */
    protected array $items = [];

    protected ?Coupon $appliedCoupon = null;

    public function addItem(Product $product, int $quantity = 1): void
    {
        if (isset($this->items[$product->id])) {
            $this->items[$product->id]['quantity'] += $quantity;
        } else {
            $this->items[$product->id] = [
                'product' => $product,
                'quantity' => $quantity,
            ];
        }
    }

    public function removeItem(string $productId): void
    {
        unset($this->items[$productId]);
    }

    public function applyCoupon(Coupon $coupon): void
    {
        $this->appliedCoupon = $coupon;
    }

    public function clear(): void
    {
        $this->items = [];
        $this->appliedCoupon = null;
    }

    /**
     * Calculate shopping cart summary.
     *
     * @return array{subtotal: float, discount: float, tax: float, grand_total: float, items_count: int}
     */
    public function getSummary(float $taxRatePct = 11.0): array
    {
        $subtotal = 0.0;
        foreach ($this->items as $item) {
            $subtotal += (float) $item['product']->price * $item['quantity'];
        }

        $discount = 0.0;
        if ($this->appliedCoupon && $this->appliedCoupon->is_active) {
            if ($this->appliedCoupon->type === 'percentage') {
                $discount = ($subtotal * ($this->appliedCoupon->value / 100));
            } else {
                $discount = min($subtotal, (float) $this->appliedCoupon->value);
            }
        }

        $taxable = max(0.0, $subtotal - $discount);
        $tax = round($taxable * ($taxRatePct / 100), 2);
        $grandTotal = round($taxable + $tax, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'tax' => $tax,
            'grand_total' => $grandTotal,
            'items_count' => count($this->items),
        ];
    }
}
