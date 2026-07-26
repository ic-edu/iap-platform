<?php

namespace App\Modules\Assessment\Enums;

enum AttemptStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Abandoned = 'abandoned';
    case Evaluated = 'evaluated';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'In Progress',
            self::Completed => 'Completed (Pending Evaluation)',
            self::Abandoned => 'Abandoned',
            self::Evaluated => 'Evaluated & Scored',
        };
    }
}
