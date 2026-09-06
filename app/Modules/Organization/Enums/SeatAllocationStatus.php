<?php

namespace App\Modules\Organization\Enums;

enum SeatAllocationStatus: string
{
    case Active = 'active';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Released => 'Released',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
