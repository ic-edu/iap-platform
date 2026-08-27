<?php

namespace App\Modules\Commerce\Domain\Enums;

enum AssessmentFamily: string
{
    case Toeic = 'toeic';
    case Toefl = 'toefl';
    case Ielts = 'ielts';
    case General = 'general';
    case Vocational = 'vocational';

    public function label(): string
    {
        return match ($this) {
            self::Toeic => 'TOEIC',
            self::Toefl => 'TOEFL iBT',
            self::Ielts => 'IELTS',
            self::General => 'General Assessment',
            self::Vocational => 'Vocational Assessment',
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
