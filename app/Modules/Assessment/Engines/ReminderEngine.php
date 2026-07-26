<?php

namespace App\Modules\Assessment\Engines;

use App\Models\User;
use App\Modules\Assessment\Events\ReminderSent;
use App\Services\NotificationService;

class ReminderEngine
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Send test reminder for tomorrow.
     */
    public function sendTestReminderTomorrow(User $candidate, string $testTitle): void
    {
        $this->notificationService->send(
            $candidate,
            'Upcoming Assessment Reminder',
            "You have an upcoming assessment: {$testTitle} scheduled for tomorrow.",
            'reminder'
        );

        event(new ReminderSent($candidate, 'test_tomorrow'));
    }
}
