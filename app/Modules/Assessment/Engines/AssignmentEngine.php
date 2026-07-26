<?php

namespace App\Modules\Assessment\Engines;

use App\Models\User;
use App\Modules\Assessment\Events\AssignmentRevoked;
use App\Modules\Assessment\Events\TestAssigned;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;

class AssignmentEngine
{
    /**
     * Assign a test to a specific student user.
     */
    public function assignToUser(Test $test, User $user): Attempt
    {
        $attempt = Attempt::create([
            'test_id' => $test->id,
            'user_id' => $user->id,
            'status' => 'draft',
            'started_at' => now(),
        ]);

        event(new TestAssigned($attempt));

        return $attempt;
    }

    /**
     * Assign test to all enrolled students of a course.
     *
     * @return array<int, Attempt>
     */
    public function assignToCourse(Test $test, string $courseId): array
    {
        $students = User::whereHas('enrollments', function ($q) use ($courseId) {
            $q->where('course_id', $courseId)->where('status', 'active');
        })->get();

        $attempts = [];
        foreach ($students as $student) {
            $attempts[] = $this->assignToUser($test, $student);
        }

        return $attempts;
    }

    /**
     * Revoke test assignment attempt.
     */
    public function revokeAssignment(Attempt $attempt): Attempt
    {
        $attempt->update(['status' => 'cancelled']);

        event(new AssignmentRevoked($attempt));

        return $attempt;
    }
}
