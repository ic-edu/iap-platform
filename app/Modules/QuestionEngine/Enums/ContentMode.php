<?php

namespace App\Modules\QuestionEngine\Enums;

enum ContentMode: string
{
    case General = 'general';
    case DomainSpecific = 'domain_specific';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General TOEIC',
            self::DomainSpecific => 'Domain-Specific TOEIC',
        };
    }

    public function isDomainSpecific(): bool
    {
        return $this === self::DomainSpecific;
    }

    public function defaultDomain(): DomainTaxonomy
    {
        return DomainTaxonomy::GeneralWorkplace;
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $mode) => [$mode->value => $mode->label()])
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
