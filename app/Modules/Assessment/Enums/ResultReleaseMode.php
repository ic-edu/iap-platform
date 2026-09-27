<?php

namespace App\Modules\Assessment\Enums;

enum ResultReleaseMode: string
{
    case Automatic = 'automatic';
    case RaControlled = 'ra_controlled';

    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Automatic Release',
            self::RaControlled => 'RA Controlled',
        };
    }

    public function isAutomatic(): bool
    {
        return $this === self::Automatic;
    }

    public function isRaControlled(): bool
    {
        return $this === self::RaControlled;
    }
}
