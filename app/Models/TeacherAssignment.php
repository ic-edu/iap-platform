<?php

namespace App\Models;

use App\Modules\Academic\Models\Course;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Teacher Assignment model for assigning teachers into active master courses.
 *
 * @property string $id
 * @property string $course_id
 * @property int $teacher_id
 * @property string $role
 * @property int|null $assigned_by
 * @property \Illuminate\Support\Carbon|null $assigned_at
 * @property string $status
 * @property string|null $assignment_notes
 */
class TeacherAssignment extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'teacher_assignments';

    protected $fillable = [
        'teacher_id',
        'course_id',
        'role',
        'assigned_by',
        'assigned_at',
        'status',
        'assignment_notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    /**
     * Get assigned teacher user.
     *
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get master course.
     *
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    /**
     * Get the user who assigned the teacher.
     *
     * @return BelongsTo<User, $this>
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
