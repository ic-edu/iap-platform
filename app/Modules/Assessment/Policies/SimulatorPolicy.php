<?php

namespace App\Modules\Assessment\Policies;

class SimulatorPolicy extends AssessmentModePolicy
{
    public function canSkipQuestion(): bool
    {
        return true;
    }

    public function canGoPrevious(): bool
    {
        return true;
    }

    public function canJumpToQuestion(): bool
    {
        return true;
    }

    public function requiresAnswerBeforeNext(): bool
    {
        return false;
    }

    public function shouldRevisitUnanswered(): bool
    {
        return true;
    }

    public function canRetry(): bool
    {
        return true;
    }

    public function requiresPaymentBeforeAssignment(): bool
    {
        return false;
    }

    public function requiresFullscreen(): bool
    {
        return false;
    }

    public function canReplayAudio(): bool
    {
        return true;
    }
}
