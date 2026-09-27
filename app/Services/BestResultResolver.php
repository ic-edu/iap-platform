<?php

namespace App\Services;

use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Certificate\Engines\CertificateEngine;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BestResultResolver
{
    /**
     * Resolve the winning attempt for a candidate test assignment.
     *
     * Invariants & Rules:
     * 1. Evaluates all completed (Submitted/Expired) attempts for the assignment.
     * 2. Requires that all completed attempts have RELEASED results before resolution.
     * 3. Compares total_score (higher total score wins).
     * 4. Deterministic tie-break: Earlier canonical completion timestamp wins, then lower attempt_number.
     * 5. Idempotently marks the winning attempt as is_final = true, decision_status = 'finalized'.
     * 6. Marks losing attempt(s) as is_final = false, decision_status = 'retried' (historical).
     * 7. Updates assignment final_attempt_id, status = 'completed', completed_at = now().
     * 8. Issues digital certificate for authoritative final result.
     */
    public function resolve(CandidateTestAssignment $assignment): Attempt
    {
        return DB::transaction(function () use ($assignment) {
            $lockedAssignment = CandidateTestAssignment::where('id', $assignment->id)->lockForUpdate()->firstOrFail();

            $attempts = $lockedAssignment->attempts()
                ->whereIn('status', [AttemptStatus::Submitted, AttemptStatus::Expired])
                ->lockForUpdate()
                ->get();

            if ($attempts->isEmpty()) {
                throw new InvalidArgumentException('Cannot resolve best result: Assignment has no submitted or completed attempts.');
            }

            // Release guard: All completed attempts must be released before best result resolution
            foreach ($attempts as $att) {
                if (!$att->isResultReleased()) {
                    throw new InvalidArgumentException("Cannot resolve best result: Attempt #{$att->attempt_number} result has not been released yet.");
                }

                if ($att->isPendingEvaluation()) {
                    throw new InvalidArgumentException("Cannot resolve best result: Attempt #{$att->attempt_number} is still awaiting examiner evaluation.");
                }
            }

            // If already resolved idempotently, return existing final attempt
            if ($lockedAssignment->status === 'completed' && $lockedAssignment->final_attempt_id) {
                $existingWinner = $attempts->firstWhere('id', $lockedAssignment->final_attempt_id);
                if ($existingWinner && $existingWinner->is_final) {
                    return $existingWinner;
                }
            }

            if ($attempts->count() === 1) {
                $winner = $attempts->first();
            } else {
                // Sort by total_score DESC, then earlier completion timestamp ASC, then attempt_number ASC
                $sorted = $attempts->sort(function (Attempt $a, Attempt $b) {
                    $scoreA = (float) ($a->total_score ?? 0);
                    $scoreB = (float) ($b->total_score ?? 0);

                    if ($scoreA !== $scoreB) {
                        return $scoreB <=> $scoreA; // DESC (higher score wins)
                    }

                    // Tie-breaker: earlier canonical completion timestamp wins
                    $timeA = $a->getCanonicalCompletionTimestamp()?->timestamp ?? ($a->submitted_at?->timestamp ?? 0);
                    $timeB = $b->getCanonicalCompletionTimestamp()?->timestamp ?? ($b->submitted_at?->timestamp ?? 0);

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
                        'is_final' => true,
                        'decision_status' => 'finalized',
                    ]);
                } else {
                    $attempt->update([
                        'is_final' => false,
                        'decision_status' => 'retried',
                    ]);
                }
            }

            $lockedAssignment->update([
                'status' => 'completed',
                'completed_at' => $lockedAssignment->completed_at ?? now(),
                'final_attempt_id' => $winner->id,
            ]);

            try {
                ActivityLogger::log(
                    'BEST_RESULT_RESOLVED',
                    "Resolved best result for assignment {$lockedAssignment->id}: Winner Attempt #{$winner->attempt_number} ({$winner->total_score} pts)",
                    $winner,
                    [
                        'assignment_id' => $lockedAssignment->id,
                        'winner_attempt_id' => $winner->id,
                        'winner_attempt_number' => $winner->attempt_number,
                        'winner_total_score' => $winner->total_score,
                    ],
                    $winner->user_id
                );
            } catch (\Throwable $e) {
                // Silently preserve transaction on logging failures
            }

            // Issue authoritative certificate for final winning result if eligible
            app(CertificateEngine::class)->issueCertificateForFinalResult($lockedAssignment->fresh());

            return $winner->fresh();
        });
    }
}
