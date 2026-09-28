<?php

namespace App\Modules\QuestionEngine\Enums;

enum GenerationBatchStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Processing = 'processing';
    case PartiallyCompleted = 'partially_completed';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Queued => 'Queued',
            self::Processing => 'Processing',
            self::PartiallyCompleted => 'Partially Completed',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::PartiallyCompleted, self::Failed, self::Cancelled], true);
    }

    public function canProcess(): bool
    {
        return in_array($this, [self::Draft, self::Queued, self::Processing, self::PartiallyCompleted, self::Failed], true);
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::Draft, self::Queued, self::Processing], true);
    }
}
