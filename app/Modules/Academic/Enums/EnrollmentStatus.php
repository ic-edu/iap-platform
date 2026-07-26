<?php

namespace App\Modules\Academic\Enums;

enum EnrollmentStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active Enrollment',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Suspended => 'Suspended',
        };
    }
}
