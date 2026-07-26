<?php

namespace App\Modules\Commerce\Domain\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Pending = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active License',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
            self::Pending => 'Pending Payment',
        };
    }
}
