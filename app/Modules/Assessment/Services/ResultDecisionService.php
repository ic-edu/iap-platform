<?php

namespace App\Modules\Assessment\Services;

use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Certificate\Engines\CertificateEngine;
use App\Services\ActivityLogger;
use App\Services\BestResultResolver;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ResultDecisionService
{
    /**
     * Finalize Attempt #1 result manually by candidate or automatically via 72h expiry.
     *
     * @throws InvalidArgumentException
     */
    public function finalizeFirstAttempt(
        CandidateTestAssignment $assignment,
        Attempt $attempt,
        string $reason = 'candidate'
    ): Attempt {
        return DB::transaction(function () use ($assignment, $attempt, $reason) {
            $lockedAttempt = Attempt::where('id', $attempt->id)->lockForUpdate()->firstOrFail();
            $lockedAssignment = CandidateTestAssignment::where('id', $assignment->id)->lockForUpdate()->firstOrFail();

            // Idempotent return if already finalized
            if ($lockedAttempt->is_final && $lockedAttempt->decision_status === 'finalized' && $lockedAssignment->status === 'completed') {
                return $lockedAttempt;
            }

            // Invariants check
            if ($lockedAttempt->assignment_id !== $lockedAssignment->id) {
                throw new InvalidArgumentException('Attempt does not belong to the specified assignment.');
            }

            if ($lockedAttempt->attempt_number !== 1) {
                throw new InvalidArgumentException('Only Attempt #1 can be finalized via this method.');
            }

            if (!$lockedAttempt->isCompleted()) {
                throw new InvalidArgumentException('Only completed attempts can be finalized.');
            }

            if (!$lockedAttempt->isResultReleased()) {
                throw new InvalidArgumentException('Attempt result must be released before finalization.');
            }

            if ($lockedAssignment->status !== 'active' && !($lockedAttempt->is_final && $lockedAttempt->decision_status === 'finalized')) {
                throw new InvalidArgumentException('Assessment assignment is no longer active.');
            }

            // Check if Attempt #2 already exists
            $hasSecondAttempt = $lockedAssignment->attempts()->where('attempt_number', 2)->exists();
            if ($hasSecondAttempt) {
                throw new InvalidArgumentException('Cannot finalize Attempt #1: A second attempt already exists.');
            }

            $lockedAttempt->update([
                'is_final' => true,
                'decision_status' => 'finalized',
            ]);

            $lockedAssignment->update([
                'status' => 'completed',
                'completed_at' => $lockedAssignment->completed_at ?? now(),
                'final_attempt_id' => $lockedAttempt->id,
            ]);

            $action = $reason === 'auto_expired' ? 'CANDIDATE_ATTEMPT_AUTO_FINALIZED' : 'CANDIDATE_ATTEMPT_FINALIZED';
            $description = $reason === 'auto_expired'
                ? "Auto-finalized expired Attempt #1 for '{$lockedAttempt->test?->title}' (72h window elapsed)"
                : "Candidate finalized Attempt #1 for '{$lockedAttempt->test?->title}'";

            try {
                ActivityLogger::log(
                    action: $action,
                    description: $description,
                    subject: $lockedAttempt,
                    properties: [
                        'assignment_id' => $lockedAssignment->id,
                        'attempt_id' => $lockedAttempt->id,
                        'attempt_number' => 1,
                        'total_score' => $lockedAttempt->total_score,
                        'reason' => $reason,
                        'finalized_at' => now()->toIso8601String(),
                    ],
                    userId: $lockedAttempt->user_id
                );
            } catch (\Throwable $e) {
                // Silently preserve transaction on logging failures
            }

            // Issue digital certificate for authoritative final result if eligible
            app(CertificateEngine::class)->issueCertificateForFinalResult($lockedAssignment->fresh());

            return $lockedAttempt->fresh();
        });
    }

    /**
     * Authoritative best-result resolution after Attempt #2 is released.
     *
     * @throws InvalidArgumentException
     */
    public function resolveAfterSecondAttempt(CandidateTestAssignment $assignment): Attempt
    {
        return app(BestResultResolver::class)->resolve($assignment);
    }
}
