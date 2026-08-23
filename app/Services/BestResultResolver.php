<?php

namespace App\Services;

use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BestResultResolver
{
    /**
     * Resolve the winning attempt for a candidate test assignment.
     *
     * Rules:
     * 1. Evaluates all submitted attempts for the assignment.
     * 2. Compares total_score (higher valid score wins).
     * 3. Deterministic tie-break: Earlier completed attempt (submitted_at ASC, then attempt_number ASC) wins.
     * 4. Idempotently marks the winning attempt as is_final = true, decision_status = 'finalized'.
     * 5. Marks losing attempt(s) as is_final = false, decision_status = 'retried' (historical).
     * 6. Updates assignment final_attempt_id, status = 'completed', completed_at = now().
     */
    public function resolve(CandidateTestAssignment $assignment): Attempt
    {
        return DB::transaction(function () use ($assignment) {
            $attempts = $assignment->attempts()
                ->where('status', AttemptStatus::Submitted)
                ->get();

            if ($attempts->isEmpty()) {
                throw new InvalidArgumentException("Cannot resolve best result: Assignment has no submitted attempts.");
            }

            if ($attempts->count() === 1) {
                $winner = $attempts->first();
            } else {
                // Sort by total_score DESC, then submitted_at ASC (earlier submitted wins ties), then attempt_number ASC
                $sorted = $attempts->sort(function (Attempt $a, Attempt $b) {
                    $scoreA = (float) ($a->total_score ?? 0);
                    $scoreB = (float) ($b->total_score ?? 0);

                    if ($scoreA !== $scoreB) {
                        return $scoreB <=> $scoreA; // DESC (higher score wins)
                    }

                    // Tie-breaker: Earlier submitted_at wins
                    $timeA = $a->submitted_at ? $a->submitted_at->timestamp : 0;
                    $timeB = $b->submitted_at ? $b->submitted_at->timestamp : 0;

                    if ($timeA !== $timeB) {
                        return $timeA <=> $timeB; // ASC (earlier is smaller timestamp)
                    }

                    return $a->attempt_number <=> $b->attempt_number; // ASC
                });

                $winner = $sorted->first();
            }

            // Update attempts state
            foreach ($attempts as $attempt) {
                if ($attempt->id === $winner->id) {
                    $attempt->update([
                        'is_final'        => true,
                        'decision_status' => 'finalized',
                    ]);
                } else {
                    $attempt->update([
                        'is_final'        => false,
                        'decision_status' => 'retried',
                    ]);
                }
            }

            $assignment->update([
                'status'           => 'completed',
                'completed_at'     => $assignment->completed_at ?? now(),
                'final_attempt_id' => $winner->id,
            ]);

            \App\Services\ActivityLogger::log(
                'BEST_RESULT_RESOLVED',
                "Resolved best result for assignment {$assignment->id}: Winner Attempt #{$winner->attempt_number} ({$winner->total_score} pts)",
                $winner
            );

            // Issue authoritative certificate for final winning result if eligible
            app(\App\Modules\Certificate\Engines\CertificateEngine::class)->issueCertificateForFinalResult($assignment->fresh());

            return $winner->fresh();
        });
    }
}
