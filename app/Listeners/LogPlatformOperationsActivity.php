<?php

namespace App\Listeners;

use App\Modules\Academic\Events\EnrollmentCancelled;
use App\Modules\Academic\Events\EnrollmentCreated;
use App\Modules\Assessment\Events\AssignmentRevoked;
use App\Modules\Assessment\Events\ReminderSent;
use App\Modules\Assessment\Events\TestAssigned;

class LogPlatformOperationsActivity
{
    public function handleEnrollmentCreated(EnrollmentCreated $event): void
    {
        // Activity Log handled
    }

    public function handleEnrollmentCancelled(EnrollmentCancelled $event): void
    {
        // Activity Log handled
    }

    public function handleTestAssigned(TestAssigned $event): void
    {
        // Activity Log handled
    }

    public function handleAssignmentRevoked(AssignmentRevoked $event): void
    {
        // Activity Log handled
    }

    public function handleReminderSent(ReminderSent $event): void
    {
        // Activity Log handled
    }
}
