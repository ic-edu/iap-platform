<?php

namespace App\Modules\QuestionEngine\Enums;

enum ToeflLanguageUseContext: string
{
    case Academic = 'academic';
    case AcademicNavigational = 'academic_navigational';
    case SocialInterpersonal = 'social_interpersonal';

    public function label(): string
    {
        return match ($this) {
            self::Academic => 'Academic',
            self::AcademicNavigational => 'Academic / Navigational',
            self::SocialInterpersonal => 'Social / Interpersonal',
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
