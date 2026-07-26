<?php

namespace App\Http\Resources;

use App\Modules\Academic\Models\CourseEnrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CourseEnrollment
 */
class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'user_id' => $this->user_id,
            'status' => $this->status->value ?? $this->status,
            'enrolled_at' => $this->enrolled_at?->toIso8601String(),
        ];
    }
}
