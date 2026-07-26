<?php

namespace App\Modules\Finance\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Payment',
            self::Paid => 'Paid & Confirmed',
            self::Failed => 'Failed / Expired',
            self::Refunded => 'Refunded',
        };
    }
}
