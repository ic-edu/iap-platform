<?php

namespace App\Modules\Academic\Events;

use App\Modules\Academic\Models\CourseEnrollment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EnrollmentCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CourseEnrollment $enrollment
    ) {}
}
