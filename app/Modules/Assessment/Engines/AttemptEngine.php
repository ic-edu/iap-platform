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
use App\Modules\Assessment\Models\Test;
use App\Modules\Certificate\Engines\CertificateEngine;
use Illuminate\Support\Str;

class AttemptEngine
{
    protected CertificateEngine $certificateEngine;

    protected TimerEngine $timerEngine;

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

        $evaluationStatus = $test->requiresEvaluation()
            ? EvaluationStatus::PendingEvaluation
            : EvaluationStatus::NotRequired;

        $attempt = Attempt::create([
            'test_id' => $test->id,
            'user_id' => $user->id,
            'started_at' => now(),
            'status' => AttemptStatus::InProgress,
            'evaluation_status' => $evaluationStatus,
            'seed' => Str::random(10),
        ]);

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
        $attempt->loadMissing(['test']);
        $test = $attempt->test;
        $requiresEvaluation = $test?->requiresEvaluation() ?? false;

        $this->scoringEngine->evaluateAttempt($attempt);

        $evaluationStatus = $requiresEvaluation
            ? EvaluationStatus::PendingEvaluation
            : EvaluationStatus::NotRequired;

        $attempt->update([
            'status' => AttemptStatus::Submitted,
            'evaluation_status' => $evaluationStatus,
            'submitted_at' => now(),
        ]);

        $attempt->refresh();
        $result = $this->resultEngine->generateResult($attempt);

        // Certificate is ONLY issued if evaluation is not pending and score passed
        if (!$requiresEvaluation && $result['is_passed']) {
            $this->certificateEngine->issueCertificate($attempt);
        }

        event(new AttemptSubmitted($attempt));

        return $attempt;
    }

    /**
     * Mark an attempt as expired due to time timeout.
     */
    public function expireAttempt(Attempt $attempt): Attempt
    {
        $attempt->loadMissing(['test']);
        $test = $attempt->test;
        $requiresEvaluation = $test?->requiresEvaluation() ?? false;

        $this->scoringEngine->evaluateAttempt($attempt);

        $evaluationStatus = $requiresEvaluation
            ? EvaluationStatus::PendingEvaluation
            : EvaluationStatus::NotRequired;

        $attempt->update([
            'status' => AttemptStatus::Expired,
            'evaluation_status' => $evaluationStatus,
            'submitted_at' => now(),
        ]);

        $attempt->refresh();
        $result = $this->resultEngine->generateResult($attempt);

        // Certificate is ONLY issued if evaluation is not pending and score passed
        if (!$requiresEvaluation && $result['is_passed']) {
            $this->certificateEngine->issueCertificate($attempt);
        }

        event(new AttemptExpired($attempt));

        return $attempt;
    }

    /**
     * Cancel an attempt.
     */
    public function cancelAttempt(Attempt $attempt): Attempt
    {
        $attempt->update([
            'status' => AttemptStatus::Cancelled,
        ]);

        return $attempt;
    }
}
