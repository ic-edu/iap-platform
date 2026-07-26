<?php

namespace App\Modules\Academic\Listeners;

use App\Modules\Academic\Events\CourseCreated;
use App\Services\ActivityLogger;

class LogCourseCreated
{
    public function handle(CourseCreated $event): void
    {
        ActivityLogger::log(
            action: 'academic.course_created',
            description: "Course '{$event->course->title}' was created.",
            subject: $event->course
        );
    }
}
