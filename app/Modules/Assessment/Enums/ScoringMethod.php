<?php

namespace App\Modules\Assessment\Enums;

enum ScoringMethod: string
{
    case Automatic = 'automatic';
    case Human = 'human';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Automatic',
            self::Human => 'Human',
            self::Hybrid => 'Hybrid',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Automatic => 'Automatically scored; no examiner required.',
            self::Human => 'Requires examiner evaluation before final result.',
            self::Hybrid => 'Automatic scoring plus examiner evaluation.',
        };
    }
}
