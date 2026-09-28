<?php

namespace App\Modules\QuestionEngine\Enums;

enum DomainTaxonomy: string
{
    case GeneralWorkplace = 'general_workplace';
    case Hospitality = 'hospitality';
    case Engineering = 'engineering';
    case Law = 'law';
    case EconomicsFinance = 'economics_finance';
    case Healthcare = 'healthcare';
    case Manufacturing = 'manufacturing';
    case Logistics = 'logistics';
    case InformationTechnology = 'information_technology';
    case Retail = 'retail';
    case Aviation = 'aviation';

    public function label(): string
    {
        return match ($this) {
            self::GeneralWorkplace => 'General Workplace',
            self::Hospitality => 'Hospitality & Tourism',
            self::Engineering => 'Engineering & Technical',
            self::Law => 'Law & Legal Services',
            self::EconomicsFinance => 'Economics & Finance',
            self::Healthcare => 'Healthcare & Medical',
            self::Manufacturing => 'Manufacturing & Production',
            self::Logistics => 'Logistics & Supply Chain',
            self::InformationTechnology => 'Information Technology',
            self::Retail => 'Retail & Sales',
            self::Aviation => 'Aviation & Aerospace',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $domain) => [$domain->value => $domain->label()])
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
