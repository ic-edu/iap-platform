<?php

namespace App\Modules\QuestionEngine\Enums;

enum ContextTaxonomy: string
{
    case Office = 'office';
    case Meeting = 'meeting';
    case Email = 'email';
    case Announcement = 'announcement';
    case Schedule = 'schedule';
    case Travel = 'travel';
    case Reservation = 'reservation';
    case CustomerService = 'customer_service';
    case Sales = 'sales';
    case Purchasing = 'purchasing';
    case Shipping = 'shipping';
    case HumanResources = 'human_resources';
    case Training = 'training';
    case Finance = 'finance';
    case Maintenance = 'maintenance';
    case Conference = 'conference';

    public function label(): string
    {
        return match ($this) {
            self::Office => 'Office Environment',
            self::Meeting => 'Business Meetings & Briefings',
            self::Email => 'Email & Correspondence',
            self::Announcement => 'Announcements & Public Notices',
            self::Schedule => 'Schedules & Agendas',
            self::Travel => 'Business Travel & Transportation',
            self::Reservation => 'Bookings & Reservations',
            self::CustomerService => 'Customer Service & Client Support',
            self::Sales => 'Sales & Marketing',
            self::Purchasing => 'Purchasing & Procurement',
            self::Shipping => 'Shipping & Logistics',
            self::HumanResources => 'Human Resources & Personnel',
            self::Training => 'Corporate Training & Workshops',
            self::Finance => 'Corporate Finance & Accounting',
            self::Maintenance => 'Facilities & Technical Maintenance',
            self::Conference => 'Conferences & Conventions',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $context) => [$context->value => $context->label()])
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
