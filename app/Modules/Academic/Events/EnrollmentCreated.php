<?php

namespace App\Modules\Academic\Events;

use App\Modules\Academic\Models\CourseEnrollment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EnrollmentCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CourseEnrollment $enrollment
    ) {}
}
