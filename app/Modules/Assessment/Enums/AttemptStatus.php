<?php

namespace App\Modules\Assessment\Enums;

enum AttemptStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft Attempt',
            self::InProgress => 'In Progress',
            self::Submitted => 'Submitted & Completed',
            self::Expired => 'Time Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isCompleted(): bool
    {
        return in_array($this, [self::Submitted, self::Expired], true);
    }
}
