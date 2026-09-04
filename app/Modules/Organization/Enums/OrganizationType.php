<?php

namespace App\Modules\Organization\Enums;

enum OrganizationType: string
{
    case School = 'school';
    case University = 'university';
    case Company = 'company';
    case Government = 'government';
    case TrainingInstitution = 'training_institution';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::School => 'School (K-12)',
            self::University => 'University / College',
            self::Company => 'Company / Enterprise',
            self::Government => 'Government Agency',
            self::TrainingInstitution => 'Training Institution',
            self::Other => 'Other Organization',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
