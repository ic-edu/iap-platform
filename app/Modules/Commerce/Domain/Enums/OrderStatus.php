<?php

namespace App\Modules\Commerce\Domain\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Payment',
            self::Completed => 'Completed Order',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
        };
    }
}
