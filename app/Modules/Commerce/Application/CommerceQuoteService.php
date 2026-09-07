<?php

namespace App\Modules\Commerce\Application;

use App\Models\User;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Organization\Models\Organization;

class CommerceQuoteService
{
    public function __construct(
        protected CouponEngine $couponEngine,
        protected PricingEngine $pricingEngine
    ) {}

    /**
     * Calculate an authoritative pricing quote with optional voucher validation.
     *
     * @return array{
     *     valid: bool,
     *     base_price: float,
     *     discount: float,
     *     taxable_amount: float,
     *     tax: float,
     *     grand_total: float,
     *     voucher: array{
     *         applied: bool,
     *         code: string|null,
     *         type: string|null,
     *         value: float|null,
     *         campaign_name: string|null,
     *         reason: string|null
     *     }
     * }
     */
    public function getQuote(
        Product $product,
        int $quantity = 1,
        ?string $voucherCode = null,
        ?User $user = null,
        ?Organization $organization = null,
        float $taxRatePct = 11.0
    ): array {
        $quantity = max(1, $quantity);
        $coupon = null;
        $voucherResult = [
            'applied'       => false,
            'code'          => null,
            'type'          => null,
            'value'         => null,
            'campaign_name' => null,
            'reason'        => null,
        ];

        if (!empty($voucherCode)) {
            $validation = $this->couponEngine->validateCoupon($voucherCode, $product, $user, $organization);
            if ($validation['valid'] && $validation['coupon']) {
                $coupon = $validation['coupon'];
                $voucherResult = [
                    'applied'       => true,
                    'code'          => $coupon->code,
                    'type'          => $coupon->type,
                    'value'         => (float) $coupon->value,
                    'campaign_name' => $coupon->campaign?->name,
                    'reason'        => null,
                ];
            } else {
                $voucherResult = [
                    'applied'       => false,
                    'code'          => strtoupper(trim($voucherCode)),
                    'type'          => null,
                    'value'         => null,
                    'campaign_name' => null,
                    'reason'        => $validation['reason'] ?? 'Invalid voucher code.',
                ];
            }
        }

        $pricing = $this->pricingEngine->calculate($product, $quantity, $coupon, $taxRatePct);
        $taxableAmount = max(0.0, $pricing['base_price'] - $pricing['discount']);

        return [
            'valid'          => $voucherResult['applied'] || empty($voucherCode),
            'base_price'     => $pricing['base_price'],
            'discount'       => $pricing['discount'],
            'taxable_amount' => round($taxableAmount, 2),
            'tax'            => $pricing['tax'],
            'grand_total'    => $pricing['grand_total'],
            'voucher'        => $voucherResult,
        ];
    }
}
