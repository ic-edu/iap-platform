<?php

namespace App\Modules\QuestionEngine\Enums;

enum ToeflItemScoringCategory: string
{
    case Scored = 'scored';
    case Pretest = 'pretest';
    case Unspecified = 'unspecified';

    public function label(): string
    {
        return match ($this) {
            self::Scored => 'Scored Operational Item',
            self::Pretest => 'Pretest / Non-Scored Item',
            self::Unspecified => 'Unknown / Unspecified at Authoring',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
