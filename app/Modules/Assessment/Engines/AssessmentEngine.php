<?php

namespace App\Modules\Assessment\Engines;

use App\Models\User;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;

class AssessmentEngine
{
    public function __construct(
        public AttemptEngine $attemptEngine,
        public TimerEngine $timerEngine,
        public NavigationEngine $navigationEngine,
        public AutoSaveEngine $autoSaveEngine,
        public RandomizationEngine $randomizationEngine,
        public ScoringEngine $scoringEngine,
        public ReviewEngine $reviewEngine
    ) {}

    public function startAttempt(Test $test, User $user): Attempt
    {
        return $this->attemptEngine->startAttempt($test, $user);
    }

    public function resumeAttempt(Attempt $attempt): Attempt
    {
        if ($this->timerEngine->isExpired($attempt)) {
            return $this->attemptEngine->expireAttempt($attempt);
        }

        return $this->attemptEngine->resumeAttempt($attempt);
    }

    public function submitAttempt(Attempt $attempt): Attempt
    {
        return $this->attemptEngine->submitAttempt($attempt);
    }

    public function cancelAttempt(Attempt $attempt): Attempt
    {
        return $this->attemptEngine->cancelAttempt($attempt);
    }

    /**
     * Get review payload.
     *
     * @return array<string, mixed>
     */
    public function reviewAttempt(Attempt $attempt): array
    {
        return $this->reviewEngine->getReviewSummary($attempt);
    }
}
