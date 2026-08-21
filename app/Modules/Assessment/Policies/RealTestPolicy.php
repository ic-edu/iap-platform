<?php

namespace App\Modules\Assessment\Policies;

class RealTestPolicy extends AssessmentModePolicy
{
    public function canSkipQuestion(): bool
    {
        return false;
    }

    public function canGoPrevious(): bool
    {
        return false;
    }

    public function canJumpToQuestion(): bool
    {
        return false;
    }

    public function requiresAnswerBeforeNext(): bool
    {
        return true;
    }

    public function shouldRevisitUnanswered(): bool
    {
        return false;
    }

    public function canRetry(): bool
    {
        return false;
    }

    public function requiresPaymentBeforeAssignment(): bool
    {
        return true;
    }

    public function requiresFullscreen(): bool
    {
        return true;
    }

    public function canReplayAudio(): bool
    {
        return false;
    }
}
