<?php

namespace App\Modules\Academic\Events;

use App\Modules\Academic\Models\Course;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CourseCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Course $course
    ) {}
}
