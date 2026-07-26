<?php

namespace App\Services;

use App\Modules\Certificate\Models\Certificate;

class VerificationService
{
    /**
     * Verify certificate by number or verification code.
     *
     * @return array<string, mixed>
     */
    public function verify(string $codeOrNumber): array
    {
        $code = trim($codeOrNumber);

        $certificate = Certificate::with(['user', 'attempt.test'])
            ->where('certificate_number', $code)
            ->orWhere('verification_code', $code)
            ->orWhere('id', $code)
            ->first();

        if (!$certificate) {
            return [
                'status' => 'not_found',
                'message' => 'Certificate not found in official registry.',
                'certificate' => null,
            ];
        }

        if ($certificate->status->value === 'revoked') {
            return [
                'status' => 'revoked',
                'message' => 'This certificate has been revoked by the issuing authority.',
                'certificate' => $certificate,
            ];
        }

        if ($certificate->expires_at && now()->greaterThan($certificate->expires_at)) {
            return [
                'status' => 'expired',
                'message' => 'This certificate has expired.',
                'certificate' => $certificate,
            ];
        }

        return [
            'status' => 'valid',
            'message' => 'Official Authentic Certificate verified.',
            'certificate' => $certificate,
        ];
    }
}
