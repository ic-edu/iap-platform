<?php

namespace App\Modules\Assessment\Engines;

use App\Models\User;
use App\Modules\Assessment\Enums\AttemptStatus;
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

    protected ResultEngine $resultEngine;

    public function __construct(
        protected ScoringEngine $scoringEngine,
        ?CertificateEngine $certificateEngine = null,
        ?ResultEngine $resultEngine = null
    ) {
        $this->certificateEngine = $certificateEngine ?? app(CertificateEngine::class);
        $this->resultEngine = $resultEngine ?? app(ResultEngine::class);
    }

    /**
     * Start a new attempt for a test.
     */
    public function startAttempt(Test $test, User $user): Attempt
    {
        $attempt = Attempt::create([
            'test_id' => $test->id,
            'user_id' => $user->id,
            'started_at' => now(),
            'status' => AttemptStatus::InProgress,
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
        $this->scoringEngine->evaluateAttempt($attempt);

        $attempt->update([
            'status' => AttemptStatus::Submitted,
            'submitted_at' => now(),
        ]);

        $attempt->refresh();
        $result = $this->resultEngine->generateResult($attempt);

        if ($result['is_passed']) {
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
        $this->scoringEngine->evaluateAttempt($attempt);

        $attempt->update([
            'status' => AttemptStatus::Expired,
            'submitted_at' => now(),
        ]);

        $attempt->refresh();
        $result = $this->resultEngine->generateResult($attempt);

        if ($result['is_passed']) {
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
