<?php

namespace App\Modules\Commerce\Domain\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Verification',
            self::Success, self::Paid => 'Payment Successful',
            self::Failed => 'Payment Failed',
            self::Refunded => 'Refunded',
        };
    }

    public function isSuccess(): bool
    {
        return $this === self::Success || $this === self::Paid;
    }
}
