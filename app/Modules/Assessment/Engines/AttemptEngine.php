<?php

namespace App\Modules\Assessment\Engines;

use App\Models\User;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\EvaluationStatus;
use App\Modules\Assessment\Events\AttemptExpired;
use App\Modules\Assessment\Events\AttemptResumed;
use App\Modules\Assessment\Events\AttemptStarted;
use App\Modules\Assessment\Events\AttemptSubmitted;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Certificate\Engines\CertificateEngine;
use App\Services\BestResultResolver;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AttemptEngine
{
    protected CertificateEngine $certificateEngine;

    protected TimerEngine $timerEngine;

    protected ResultEngine $resultEngine;

    public function __construct(
        protected ScoringEngine $scoringEngine,
        ?CertificateEngine $certificateEngine = null,
        ?ResultEngine $resultEngine = null,
        ?TimerEngine $timerEngine = null
    ) {
        $this->certificateEngine = $certificateEngine ?? app(CertificateEngine::class);
        $this->resultEngine = $resultEngine ?? app(ResultEngine::class);
        $this->timerEngine = $timerEngine ?? app(TimerEngine::class);
    }

    /**
     * Start a new attempt for a test, or resume existing active unexpired attempt.
     */
    public function startAttempt(Test $test, User $user): Attempt
    {
        $existing = Attempt::where('test_id', $test->id)
            ->where('user_id', $user->id)
            ->where('status', AttemptStatus::InProgress)
            ->latest('started_at')
            ->first();

        if ($existing && !$this->timerEngine->isExpired($existing)) {
            $this->resumeAttempt($existing);
            return $existing;
        }

        $assignment = null;
        if ($test->isRealTest()) {
            $assignment = CandidateTestAssignment::where('user_id', $user->id)
                ->where('test_id', $test->id)
                ->latest('assigned_at')
                ->first();

            if ($assignment) {
                $completedCount = $assignment->attempts()->where('status', AttemptStatus::Submitted)->count();
                if ($assignment->status === 'completed' || $completedCount >= $assignment->max_attempts) {
                    throw new InvalidArgumentException("Maximum attempts ({$assignment->max_attempts}) reached for this Mock Test assignment.");
                }
            }
        }

        $evaluationStatus = $test->requiresEvaluation()
            ? EvaluationStatus::PendingEvaluation
            : EvaluationStatus::NotRequired;

        $attemptNumber = $assignment ? ($assignment->attempts()->count() + 1) : 1;

        $attempt = Attempt::create([
            'test_id'           => $test->id,
            'user_id'           => $user->id,
            'assignment_id'     => ($assignment && $assignment->status === 'active') ? $assignment->id : null,
            'attempt_number'    => $attemptNumber,
            'is_final'          => false,
            'decision_status'   => $test->isRealTest() ? 'pending_decision' : null,
            'started_at'        => now(),
            'status'            => AttemptStatus::InProgress,
            'evaluation_status' => $evaluationStatus,
            'seed'              => Str::random(10),
        ]);

        if ($assignment && $assignment->status === 'active') {
            $assignment->update([
                'attempts_count' => $assignment->attempts()->count(),
            ]);
        }

        event(new AttemptStarted($attempt));

        return $attempt;
    }

    /**
     * Resume an existing in-progress attempt.
     */
    public function resumeAttempt(Attempt $attempt): Attempt
    {
        event(new AttemptResumed($attempt));

        return $attempt;
    }

    /**
     * Final submit an attempt.
     */
    public function submitAttempt(Attempt $attempt): Attempt
    {
        $statusValue = is_object($attempt->status) ? $attempt->status->value : (string) $attempt->status;
        if ($statusValue !== AttemptStatus::InProgress->value && $statusValue !== 'in_progress') {
            return $attempt;
        }

        $attempt->loadMissing(['test', 'assignment']);
        $test = $attempt->test;
        $requiresEvaluation = $test?->requiresEvaluation() ?? false;

        $this->scoringEngine->evaluateAttempt($attempt);

        $evaluationStatus = $requiresEvaluation
            ? EvaluationStatus::PendingEvaluation
            : EvaluationStatus::NotRequired;

        $attempt->update([
            'status'            => AttemptStatus::Submitted,
            'evaluation_status' => $evaluationStatus,
            'submitted_at'      => now(),
            'decision_status'   => ($test && $test->isRealTest()) ? 'pending_decision' : null,
        ]);

        $attempt->refresh();
        $result = $this->resultEngine->generateResult($attempt);

        // Certificate Decoupling: Real Tests / Mock Tests NEVER issue certificate on raw submission
        // Simulators NEVER issue certificate.
        if ($test && !$test->isSimulator() && !$test->isRealTest() && !$requiresEvaluation && ($result['is_passed'] ?? false)) {
            $this->certificateEngine->issueCertificate($attempt);
        }

        // Best Result Resolution: If Mock Test Attempt 2+ submitted, determine the winning attempt and complete assignment
        if ($test && $test->isRealTest() && $attempt->assignment) {
            $assignment = $attempt->assignment;
            $submittedCount = $assignment->attempts()->where('status', AttemptStatus::Submitted)->count();
            if ($attempt->attempt_number >= $assignment->max_attempts || $submittedCount >= $assignment->max_attempts) {
                app(BestResultResolver::class)->resolve($assignment);
            }
        }

        event(new AttemptSubmitted($attempt));

        return $attempt;
    }

    /**
     * Mark an attempt as expired due to time timeout.
     */
    public function expireAttempt(Attempt $attempt): Attempt
    {
        $statusValue = is_object($attempt->status) ? $attempt->status->value : (string) $attempt->status;
        if ($statusValue !== AttemptStatus::InProgress->value && $statusValue !== 'in_progress') {
            return $attempt;
        }

        $attempt->loadMissing(['test', 'assignment']);
        $test = $attempt->test;
        $requiresEvaluation = $test?->requiresEvaluation() ?? false;

        $this->scoringEngine->evaluateAttempt($attempt);

        $evaluationStatus = $requiresEvaluation
            ? EvaluationStatus::PendingEvaluation
            : EvaluationStatus::NotRequired;

        $attempt->update([
            'status'            => AttemptStatus::Expired,
            'evaluation_status' => $evaluationStatus,
            'submitted_at'      => now(),
            'decision_status'   => ($test && $test->isRealTest()) ? 'pending_decision' : null,
        ]);

        $attempt->refresh();
        $this->resultEngine->generateResult($attempt);

        if ($test && $test->isRealTest() && $attempt->assignment) {
            $assignment = $attempt->assignment;
            $submittedCount = $assignment->attempts()->whereIn('status', [AttemptStatus::Submitted, AttemptStatus::Expired])->count();
            if ($attempt->attempt_number >= $assignment->max_attempts || $submittedCount >= $assignment->max_attempts) {
                app(BestResultResolver::class)->resolve($assignment);
            }
        }

        event(new AttemptExpired($attempt));

        return $attempt;
    }

    /**
     * Mark an attempt as cancelled.
     */
    public function cancelAttempt(Attempt $attempt): Attempt
    {
        $attempt->update(['status' => AttemptStatus::Cancelled]);

        return $attempt;
    }

    /**
     * Generate review summary for an attempt.
     *
     * @return array<string, mixed>
     */
    public function reviewAttempt(Attempt $attempt): array
    {
        return $this->resultEngine->generateResult($attempt);
    }
}
