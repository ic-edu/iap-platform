<?php

namespace App\Modules\Commerce\Domain\Enums;

enum PricingType: string
{
    case Fixed = 'fixed';
    case Promo = 'promo';
    case DiscountPercentage = 'discount_percentage';
    case DiscountFixed = 'discount_fixed';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Standard Fixed Price',
            self::Promo => 'Promotional Special',
            self::DiscountPercentage => 'Percentage Discount',
            self::DiscountFixed => 'Fixed Amount Discount',
        };
    }
}
