<?php

namespace App\Modules\Assessment\Policies;

use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Models\Test;

abstract class AssessmentModePolicy
{
    public function __construct(protected Test $test) {}

    public static function for(Test $test): self
    {
        return match ($test->assessment_mode ?? AssessmentMode::Simulator) {
            AssessmentMode::RealTest => new RealTestPolicy($test),
            default => new SimulatorPolicy($test),
        };
    }

    abstract public function canSkipQuestion(): bool;

    abstract public function canGoPrevious(): bool;

    abstract public function canJumpToQuestion(): bool;

    abstract public function requiresAnswerBeforeNext(): bool;

    abstract public function shouldRevisitUnanswered(): bool;

    abstract public function canRetry(): bool;

    abstract public function requiresPaymentBeforeAssignment(): bool;

    abstract public function requiresFullscreen(): bool;

    abstract public function canReplayAudio(): bool;

    abstract public function canCandidateViewDetailedQuestionReview(): bool;
}
