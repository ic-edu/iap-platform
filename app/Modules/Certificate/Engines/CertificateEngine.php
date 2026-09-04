<?php

namespace App\Modules\Certificate\Engines;

use App\Modules\Assessment\Engines\ResultEngine;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Certificate\Enums\CertificateStatus;
use App\Modules\Certificate\Events\CertificateIssued;
use App\Modules\Certificate\Events\CertificateReissued;
use App\Modules\Certificate\Events\CertificateRevoked;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Certificate\Services\CertificateEligibilityService;
use Illuminate\Support\Str;

class CertificateEngine
{
    public function __construct(
        protected ?CertificateEligibilityService $eligibilityService = null
    ) {
        $this->eligibilityService = $eligibilityService ?? app(CertificateEligibilityService::class);
    }

    /**
     * Issue digital certificate for the authoritative final attempt of a completed assignment.
     */
    public function issueCertificateForFinalResult(CandidateTestAssignment $assignment, string $template = 'internal'): ?Certificate
    {
        // 1. Verify assignment is completed and has final_attempt_id
        if ($assignment->status !== 'completed' || empty($assignment->final_attempt_id)) {
            return null;
        }

        // 2. Load and verify final attempt
        $finalAttempt = Attempt::with(['test', 'answers.question'])->find($assignment->final_attempt_id);
        if (!$finalAttempt || $finalAttempt->assignment_id !== $assignment->id) {
            return null;
        }

        // 3. Authoritative eligibility check (Simulator is NEVER eligible)
        if (!$this->eligibilityService->canIssueCertificate($finalAttempt->test, $finalAttempt)) {
            return null;
        }

        // 4. Verify attempt is completed and marked final
        if (!in_array($finalAttempt->status, [AttemptStatus::Submitted, AttemptStatus::Expired], true) || !$finalAttempt->is_final) {
            return null;
        }

        // 5. Verify evaluation is complete (not pending evaluation)
        if ($finalAttempt->isPendingEvaluation()) {
            return null;
        }

        // 6. Verify certificate eligibility (passed score threshold)
        $result = app(ResultEngine::class)->generateResult($finalAttempt);
        if (!($result['is_passed'] ?? false)) {
            return null;
        }

        // 7. Check existing certificate for final attempt (Idempotency)
        $existing = Certificate::where('attempt_id', $finalAttempt->id)->first();
        if ($existing) {
            return $existing;
        }

        // 8. Check if a certificate was already created for any attempt of this assignment
        $existingAssignmentCert = Certificate::whereIn('attempt_id', $assignment->attempts()->pluck('id'))->first();
        if ($existingAssignmentCert) {
            if ($existingAssignmentCert->attempt_id !== $finalAttempt->id) {
                $existingAssignmentCert->update(['attempt_id' => $finalAttempt->id]);
            }
            return $existingAssignmentCert;
        }

        // 9. Generate authoritative certificate
        $dateStr = now()->format('Ymd');
        $randomCode = strtoupper(Str::random(4));
        $certNumber = "CERT-{$dateStr}-{$randomCode}";
        $verifCode = 'VRF-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));

        $certificate = Certificate::create([
            'certificate_number' => $certNumber,
            'verification_code'  => $verifCode,
            'attempt_id'         => $finalAttempt->id,
            'user_id'            => $finalAttempt->user_id,
            'status'             => CertificateStatus::Valid,
            'template'           => $template,
            'issued_at'          => now(),
            'expires_at'         => now()->addYears(2),
        ]);

        event(new CertificateIssued($certificate));

        return $certificate;
    }

    /**
     * Issue digital certificate for a passed attempt (with Mock Test finality check).
     */
    public function issueCertificate(Attempt $attempt, string $template = 'internal'): ?Certificate
    {
        $attempt->loadMissing(['test', 'assignment']);
        $test = $attempt->test;

        // Authoritative eligibility check (Simulator is NEVER eligible, Mock test checks finality)
        if (!$this->eligibilityService->canIssueCertificate($test, $attempt)) {
            return null;
        }

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
