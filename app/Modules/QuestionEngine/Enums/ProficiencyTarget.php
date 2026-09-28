<?php

namespace App\Modules\QuestionEngine\Enums;

enum ProficiencyTarget: string
{
    case A1 = 'a1';
    case A1_PLUS = 'a1_plus';
    case A2_LOW = 'a2_low';
    case A2_STANDARD = 'a2_standard';
    case B1_LOW = 'b1_low';
    case B1_STANDARD = 'b1_standard';
    case B2_LOW = 'b2_low';
    case B2_STANDARD = 'b2_standard';
    case C1 = 'c1';
    case C2 = 'c2';

    public function label(): string
    {
        return match ($this) {
            self::A1 => 'A1 - Beginner',
            self::A1_PLUS => 'A1+ - Elementary',
            self::A2_LOW => 'A2 Low - Pre-Intermediate (Emerging)',
            self::A2_STANDARD => 'A2 - Pre-Intermediate (Standard)',
            self::B1_LOW => 'B1 Low - Intermediate (Emerging)',
            self::B1_STANDARD => 'B1 - Intermediate (Standard)',
            self::B2_LOW => 'B2 Low - Upper Intermediate (Emerging)',
            self::B2_STANDARD => 'B2 - Upper Intermediate (Standard)',
            self::C1 => 'C1 - Advanced',
            self::C2 => 'C2 - Proficiency',
        };
    }

    public function cefrBand(): string
    {
        return match ($this) {
            self::A1, self::A1_PLUS => 'A1',
            self::A2_LOW, self::A2_STANDARD => 'A2',
            self::B1_LOW, self::B1_STANDARD => 'B1',
            self::B2_LOW, self::B2_STANDARD => 'B2',
            self::C1 => 'C1',
            self::C2 => 'C2',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $target) => [$target->value => $target->label()])
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
