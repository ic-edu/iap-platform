<?php

namespace App\Modules\QuestionBank\Enums;

enum QuestionType: string
{
    case MultipleChoice = 'multiple_choice';
    case AudioResponse = 'audio_response';
    case Essay = 'essay';

    public function label(): string
    {
        return match ($this) {
            self::MultipleChoice => 'Multiple Choice',
            self::AudioResponse => 'Audio Response (Speaking)',
            self::Essay => 'Essay / Writing',
        };
    }
}
