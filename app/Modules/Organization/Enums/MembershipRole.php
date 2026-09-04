<?php

namespace App\Modules\Organization\Enums;

enum MembershipRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Coordinator = 'coordinator';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner / Director',
            self::Admin => 'Organization Admin',
            self::Coordinator => 'Organization Coordinator',
            self::Member => 'Candidate Member',
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
