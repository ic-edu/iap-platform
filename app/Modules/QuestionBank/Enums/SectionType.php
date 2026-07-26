<?php

namespace App\Modules\QuestionBank\Enums;

enum SectionType: string
{
    case Listening = 'listening';
    case Reading = 'reading';
    case Speaking = 'speaking';
    case Writing = 'writing';

    public function label(): string
    {
        return match ($this) {
            self::Listening => 'Listening Section',
            self::Reading => 'Reading Section',
            self::Speaking => 'Speaking Section',
            self::Writing => 'Writing Section',
        };
    }
}
