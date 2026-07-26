<?php

namespace App\Modules\Academic\Models;

use App\Models\User;
use App\Modules\Academic\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $course_id
 * @property int $user_id
 * @property Carbon|null $enrolled_at
 * @property EnrollmentStatus $status
 * @property Course|null $course
 * @property User|null $user
 */
class CourseEnrollment extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'course_enrollments';

    protected $fillable = [
        'course_id',
        'user_id',
        'enrolled_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'status' => EnrollmentStatus::class,
        ];
    }

    /**
     * Get the enrolled course.
     *
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    /**
     * Get the student user.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
