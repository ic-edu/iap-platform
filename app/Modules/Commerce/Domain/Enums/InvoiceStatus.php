<?php

namespace App\Modules\Commerce\Domain\Enums;

enum InvoiceStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::Paid => 'Paid & Verified',
            self::Expired => 'Expired Invoice',
            self::Cancelled => 'Cancelled',
        };
    }
}
