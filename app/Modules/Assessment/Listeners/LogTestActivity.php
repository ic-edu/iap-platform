<?php

namespace App\Modules\Assessment\Listeners;

use App\Modules\Assessment\Events\TestCreated;
use App\Modules\Assessment\Events\TestPublished;
use App\Services\ActivityLogger;

class LogTestActivity
{
    public function handleTestCreated(TestCreated $event): void
    {
        ActivityLogger::log(
            action: 'assessment.test_created',
            description: "Test '{$event->test->title}' was created.",
            subject: $event->test
        );
    }

    public function handleTestPublished(TestPublished $event): void
    {
        ActivityLogger::log(
            action: 'assessment.test_published',
            description: "Test '{$event->test->title}' was published.",
            subject: $event->test
        );
    }
}
