<?php

namespace App\Modules\Certificate\Services;

use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Certificate\Models\Certificate;

class CertificateEligibilityService
{
    /**
     * Check if a Test or Attempt is eligible for certificate issuance.
     */
    public function canIssueCertificate(?Test $test, ?Attempt $attempt = null): bool
    {
        if ($test === null && $attempt !== null) {
            $attempt->loadMissing('test');
            $test = $attempt->test;
        }

        if (!$test) {
            return false;
        }

        // Authoritative Policy: Simulator / Practice assessments are NEVER certificate-eligible.
        if ($test->isSimulator()) {
            return false;
        }

        // For Real / Mock tests:
        if ($test->isRealTest()) {
            if ($attempt === null) {
                return true; // Test mode is generally certificate eligible
            }

            // For assigned attempts: Must be authoritative final attempt of a completed assignment
            if (!empty($attempt->assignment_id)) {
                if (!$attempt->is_final) {
                    return false;
                }

                $attempt->loadMissing('assignment');
                $assignment = $attempt->assignment;
                if (!$assignment || $assignment->status !== 'completed' || $assignment->final_attempt_id !== $attempt->id) {
                    return false;
                }
            }

            if ($attempt->isPendingEvaluation()) {
                return false;
            }

            return true;
        }

        // Non-simulator / other assessment types: check attempt if provided
        if ($attempt !== null && $attempt->isPendingEvaluation()) {
            return false;
        }

        return true;
    }

    /**
     * Check if an existing Certificate record is valid and eligible for Candidate display/download.
     */
    public function isCertificateEligible(Certificate $certificate): bool
    {
        $certificate->loadMissing('attempt.test');
        $test = $certificate->attempt?->test;

        if (!$test) {
            return false;
        }

        if ($test->isSimulator()) {
            return false;
        }

        return true;
    }
}
