<?php

namespace App\Modules\Academic\Engines;

use App\Models\User;
use App\Modules\Academic\Enums\EnrollmentStatus;
use App\Modules\Academic\Events\EnrollmentCancelled;
use App\Modules\Academic\Events\EnrollmentCreated;
use App\Modules\Academic\Models\Course;
use App\Modules\Academic\Models\CourseEnrollment;

class EnrollmentEngine
{
    /**
     * Enroll a student into a course.
     */
    public function enrollStudent(Course $course, User $student): CourseEnrollment
    {
        $existing = CourseEnrollment::where('course_id', $course->id)
            ->where('user_id', $student->id)
            ->first();

        if ($existing) {
            $existing->update(['status' => EnrollmentStatus::Active]);

            return $existing;
        }

        $enrollment = CourseEnrollment::create([
            'course_id' => $course->id,
            'user_id' => $student->id,
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Active,
        ]);

        event(new EnrollmentCreated($enrollment));

        return $enrollment;
    }

    /**
     * Bulk enroll multiple students into a course.
     *
     * @param  array<int, int>  $studentIds
     * @return array<int, CourseEnrollment>
     */
    public function bulkEnroll(Course $course, array $studentIds): array
    {
        $enrollments = [];
        $students = User::whereIn('id', $studentIds)->get();

        foreach ($students as $student) {
            $enrollments[] = $this->enrollStudent($course, $student);
        }

        return $enrollments;
    }

    /**
     * Cancel student enrollment.
     */
    public function cancelEnrollment(CourseEnrollment $enrollment): CourseEnrollment
    {
        $enrollment->update(['status' => EnrollmentStatus::Cancelled]);

        event(new EnrollmentCancelled($enrollment));

        return $enrollment;
    }
}
