<?php

namespace App\Modules\Assessment\Enums;

enum EvaluationStatus: string
{
    case NotRequired = 'not_required';
    case PendingEvaluation = 'pending_evaluation';
    case InProgress = 'in_progress';
    case Evaluated = 'evaluated';
    case Moderated = 'moderated';

    public function label(): string
    {
        return match ($this) {
            self::NotRequired => 'Not Required',
            self::PendingEvaluation => 'Pending Evaluation',
            self::InProgress => 'In Progress',
            self::Evaluated => 'Evaluated',
            self::Moderated => 'Moderated',
        };
    }
}
