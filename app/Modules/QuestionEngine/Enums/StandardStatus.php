<?php

namespace App\Modules\QuestionEngine\Enums;

enum StandardStatus: string
{
    case Draft = 'draft';
    case Detected = 'detected';
    case PendingReview = 'pending_review';
    case Active = 'active';
    case Superseded = 'superseded';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft Definition',
            self::Detected => 'Detected External Standard',
            self::PendingReview => 'Pending Governance Review',
            self::Active => 'Active Authoritative Standard',
            self::Superseded => 'Superseded by Newer Version',
            self::Archived => 'Archived Standard',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function canBeActivated(): bool
    {
        return in_array($this, [self::Draft, self::PendingReview, self::Detected], true);
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
