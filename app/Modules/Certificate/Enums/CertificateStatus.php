<?php

namespace App\Modules\Certificate\Enums;

enum CertificateStatus: string
{
    case Valid = 'valid';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Valid & Authentic',
            self::Revoked => 'Revoked',
            self::Expired => 'Expired Certificate',
        };
    }
}
