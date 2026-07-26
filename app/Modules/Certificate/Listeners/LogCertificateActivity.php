<?php

namespace App\Modules\Certificate\Listeners;

use App\Modules\Certificate\Events\CertificateIssued;
use App\Modules\Certificate\Events\CertificateReissued;
use App\Modules\Certificate\Events\CertificateRevoked;
use App\Services\ActivityLogger;

class LogCertificateActivity
{
    public function handleCertificateIssued(CertificateIssued $event): void
    {
        ActivityLogger::log(
            action: 'certificate.issued',
            description: "Certificate '{$event->certificate->certificate_number}' issued for candidate ID {$event->certificate->user_id}",
            subject: $event->certificate
        );
    }

    public function handleCertificateReissued(CertificateReissued $event): void
    {
        ActivityLogger::log(
            action: 'certificate.reissued',
            description: "Certificate '{$event->certificate->certificate_number}' reissued.",
            subject: $event->certificate
        );
    }

    public function handleCertificateRevoked(CertificateRevoked $event): void
    {
        ActivityLogger::log(
            action: 'certificate.revoked',
            description: "Certificate '{$event->certificate->certificate_number}' revoked.",
            subject: $event->certificate
        );
    }
}
