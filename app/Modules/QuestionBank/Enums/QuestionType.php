<?php

namespace App\Modules\QuestionBank\Enums;

enum QuestionType: string
{
    case MultipleChoice = 'multiple_choice';
    case MultipleResponse = 'multiple_response';
    case TrueFalse = 'true_false';
    case ShortAnswer = 'short_answer';
    case Essay = 'essay';
    case Speaking = 'speaking';
    case Listening = 'listening';
    case Writing = 'writing';
    case Matching = 'matching';
    case Ordering = 'ordering';
    case FillInTheBlank = 'fill_in_the_blank';
    case DragAndDrop = 'drag_and_drop';

    public function label(): string
    {
        return match ($this) {
            self::MultipleChoice => 'Multiple Choice (Single Answer)',
            self::MultipleResponse => 'Multiple Response (Multi Answer)',
            self::TrueFalse => 'True / False',
            self::ShortAnswer => 'Short Answer',
            self::Essay => 'Essay / Writing',
            self::Speaking => 'Audio Response (Speaking)',
            self::Listening => 'Listening Item',
            self::Writing => 'Writing Prompt',
            self::Matching => 'Matching Options',
            self::Ordering => 'Sequence / Ordering',
            self::FillInTheBlank => 'Fill in the Blank',
            self::DragAndDrop => 'Drag & Drop Item',
        };
    }
}
