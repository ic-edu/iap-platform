<?php

namespace App\Modules\QuestionBank\Enums;

enum TestType: string
{
    case Toeic = 'toeic';
    case Toefl = 'toefl';
    case Ielts = 'ielts';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Toeic => 'TOEIC',
            self::Toefl => 'TOEFL iBT',
            self::Ielts => 'IELTS',
            self::General => 'General Assessment',
        };
    }
}
