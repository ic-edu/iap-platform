<?php

namespace App\Listeners;

use App\Modules\Academic\Events\EnrollmentCreated;

class LogPlatformOperationsActivity
{
    public function handleEnrollmentCreated(EnrollmentCreated $event): void
    {
        // Activity Log handled
    }
}
