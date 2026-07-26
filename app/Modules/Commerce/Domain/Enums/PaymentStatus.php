<?php

namespace App\Modules\Commerce\Domain\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Verification',
            self::Success => 'Payment Successful',
            self::Failed => 'Payment Failed',
            self::Refunded => 'Refunded',
        };
    }
}
