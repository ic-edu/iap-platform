<?php

namespace App\Modules\Assessment\Services;

use App\Models\User;
use App\Modules\Assessment\Enums\ResultReleaseStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Notifications\EnterpriseSystemNotification;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ResultReleaseService
{
    /**
     * Release assessment attempt result to make it visible to the candidate.
     *
     * @throws InvalidArgumentException|RuntimeException
     */
    public function release(Attempt $attempt, ?User $actor = null): Attempt
    {
        // 1. Invariants check
        if (!$attempt->isCompleted()) {
            throw new InvalidArgumentException('Only completed attempts (submitted or expired) can be released.');
        }

        $test = $attempt->test;
        if (!$test || $test->isSimulator()) {
            throw new InvalidArgumentException('Simulator assessments do not require manual result release.');
        }

        if ($attempt->isPendingEvaluation()) {
            throw new InvalidArgumentException('Attempt is still awaiting examiner evaluation and cannot be released.');
        }

        if ($attempt->result_release_at === null) {
            throw new InvalidArgumentException('Attempt does not have a configured result release time.');
        }

        // Server-side early release block
        if (now()->lessThan($attempt->result_release_at)) {
            throw new RuntimeException('Result cannot be released before the configured release time.');
        }

        // 2. Atomic release execution under row lock
        return DB::transaction(function () use ($attempt, $actor, $test) {
            $lockedAttempt = Attempt::where('id', $attempt->id)->lockForUpdate()->firstOrFail();

            // Idempotent return if already released
            if ($lockedAttempt->isResultReleased()) {
                return $lockedAttempt;
            }

            // Re-verify early release block under lock
            if (now()->lessThan($lockedAttempt->result_release_at)) {
                throw new RuntimeException('Result cannot be released before the configured release time.');
            }

            $releasedAt = now();
            $releasedBy = $actor?->id;

            $lockedAttempt->update([
                'result_release_status' => ResultReleaseStatus::Released,
                'result_released_at' => $releasedAt,
                'result_released_by' => $releasedBy,
            ]);

            // 3. Audit Logging (RESULT_RELEASED)
            try {
                ActivityLogger::log(
                    action: 'RESULT_RELEASED',
                    description: "Result released for attempt #{$lockedAttempt->id} ({$test->title}) by user #{$releasedBy}",
                    subject: $lockedAttempt,
                    properties: [
                        'attempt_id' => $lockedAttempt->id,
                        'test_id' => $lockedAttempt->test_id,
                        'candidate_user_id' => $lockedAttempt->user_id,
                        'released_by' => $releasedBy,
                        'result_release_at' => $lockedAttempt->result_release_at?->toIso8601String(),
                        'result_released_at' => $releasedAt->toIso8601String(),
                        'release_mode' => $test->result_release_mode?->value ?? 'manual',
                    ],
                    userId: $releasedBy
                );
            } catch (\Throwable $e) {
                // Silently preserve transaction on audit log logging failures
            }

            // 4. Candidate In-App Notification
            try {
                $candidate = $lockedAttempt->user;
                if ($candidate) {
                    $candidate->notify(new EnterpriseSystemNotification(
                        title: 'Mock Test Result Available',
                        message: 'Your Mock Test result is now available.',
                        type: 'RESULT_RELEASED',
                        priority: 'HIGH',
                        entityType: 'Attempt',
                        entityId: (string) $lockedAttempt->id,
                        targetUrl: route('candidate.review', $lockedAttempt)
                    ));
                }
            } catch (\Throwable $e) {
                // Silently preserve transaction on notification delivery failures
            }

            return $lockedAttempt;
        });
    }
}
