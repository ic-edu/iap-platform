<?php

namespace App\Modules\Organization\Enums;

enum OrganizationStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case NeedsRevision = 'needs_revision';
    case Active = 'active';
    case Suspended = 'suspended';
    case Rejected = 'rejected';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending Approval',
            self::NeedsRevision => 'Needs Revision',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Rejected => 'Rejected',
            self::Archived => 'Archived',
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
