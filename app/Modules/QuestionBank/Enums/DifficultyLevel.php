<?php

namespace App\Modules\QuestionBank\Enums;

enum DifficultyLevel: string
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';

    public function label(): string
    {
        return match ($this) {
            self::Easy => 'Easy',
            self::Medium => 'Medium',
            self::Hard => 'Hard / Advanced',
        };
    }
}
