<?php

namespace App\Modules\Certificate\Engines;

use App\Modules\Assessment\Models\Attempt;
use App\Modules\Certificate\Enums\CertificateStatus;
use App\Modules\Certificate\Events\CertificateIssued;
use App\Modules\Certificate\Events\CertificateReissued;
use App\Modules\Certificate\Events\CertificateRevoked;
use App\Modules\Certificate\Models\Certificate;
use Illuminate\Support\Str;

class CertificateEngine
{
    /**
     * Issue digital certificate for a passed attempt.
     */
    public function issueCertificate(Attempt $attempt, string $template = 'internal'): Certificate
    {
        $existing = Certificate::where('attempt_id', $attempt->id)->first();
        if ($existing) {
            return $existing;
        }

        $dateStr = now()->format('Ymd');
        $randomCode = strtoupper(Str::random(4));
        $certNumber = "CERT-{$dateStr}-{$randomCode}";
        $verifCode = 'VRF-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));

        $certificate = Certificate::create([
            'certificate_number' => $certNumber,
            'verification_code' => $verifCode,
            'attempt_id' => $attempt->id,
            'user_id' => $attempt->user_id,
            'status' => CertificateStatus::Valid,
            'template' => $template,
            'issued_at' => now(),
            'expires_at' => now()->addYears(2),
        ]);

        event(new CertificateIssued($certificate));

        return $certificate;
    }

    /**
     * Reissue certificate.
     */
    public function reissueCertificate(Certificate $certificate): Certificate
    {
        $certificate->update([
            'status' => CertificateStatus::Valid,
            'revoked_at' => null,
            'issued_at' => now(),
        ]);

        event(new CertificateReissued($certificate));

        return $certificate;
    }

    /**
     * Revoke certificate.
     */
    public function revokeCertificate(Certificate $certificate): Certificate
    {
        $certificate->update([
            'status' => CertificateStatus::Revoked,
            'revoked_at' => now(),
        ]);

        event(new CertificateRevoked($certificate));

        return $certificate;
    }
}
