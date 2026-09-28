<?php

namespace App\Modules\QuestionEngine\Enums;

enum ContentOrigin: string
{
    case Manual = 'manual';
    case Generated = 'generated';
    case GeneratedThenEdited = 'generated_then_edited';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual Authoring',
            self::Generated => 'AI Generated',
            self::GeneratedThenEdited => 'Generated & Edited',
        };
    }

    public function isGenerated(): bool
    {
        return $this === self::Generated || $this === self::GeneratedThenEdited;
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $origin) => [$origin->value => $origin->label()])
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
