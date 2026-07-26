<?php

namespace App\Modules\Assessment\Listeners;

use App\Modules\Assessment\Events\AttemptExpired;
use App\Modules\Assessment\Events\AttemptResumed;
use App\Modules\Assessment\Events\AttemptStarted;
use App\Modules\Assessment\Events\AttemptSubmitted;
use App\Modules\Assessment\Events\RuleViolationDetected;
use App\Services\ActivityLogger;

class LogAssessmentDeliveryActivity
{
    public function handleAttemptStarted(AttemptStarted $event): void
    {
        ActivityLogger::log(
            action: 'cbt.attempt_started',
            description: "Attempt started for test '{$event->attempt->test?->title}'",
            subject: $event->attempt
        );
    }

    public function handleAttemptResumed(AttemptResumed $event): void
    {
        ActivityLogger::log(
            action: 'cbt.attempt_resumed',
            description: "Attempt resumed for test '{$event->attempt->test?->title}'",
            subject: $event->attempt
        );
    }

    public function handleAttemptSubmitted(AttemptSubmitted $event): void
    {
        ActivityLogger::log(
            action: 'cbt.attempt_submitted',
            description: "Attempt submitted for test '{$event->attempt->test?->title}'",
            subject: $event->attempt
        );
    }

    public function handleAttemptExpired(AttemptExpired $event): void
    {
        ActivityLogger::log(
            action: 'cbt.attempt_expired',
            description: "Attempt expired for test '{$event->attempt->test?->title}'",
            subject: $event->attempt
        );
    }

    public function handleRuleViolationDetected(RuleViolationDetected $event): void
    {
        $event->attempt->increment('violations_count');

        ActivityLogger::log(
            action: 'cbt.violation_detected',
            description: "Anti-cheating violation '{$event->violationType}' detected on attempt '{$event->attempt->id}'",
            subject: $event->attempt
        );
    }
}
