<?php

namespace App\Modules\QuestionEngine\Enums;

enum GenerationItemStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Processing = 'processing';
    case Generated = 'generated';
    case ValidationFailed = 'validation_failed';
    case Validated = 'validated';
    case Materialized = 'materialized';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Ready => 'Ready',
            self::Processing => 'Processing',
            self::Generated => 'Generated',
            self::ValidationFailed => 'Validation Failed',
            self::Validated => 'Validated',
            self::Materialized => 'Materialized',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Materialized, self::Cancelled], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Materialized, self::Cancelled, self::Failed], true);
    }

    public function canAttempt(): bool
    {
        return in_array($this, [self::Pending, self::Ready, self::ValidationFailed, self::Failed], true);
    }
}
