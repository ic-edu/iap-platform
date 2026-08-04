<?php

namespace App\Modules\Academic\Models;

use App\Models\TeacherAssignment;
use App\Models\User;
use App\Modules\Academic\Enums\CourseLevel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $category_id
 * @property string $title
 * @property string $slug
 * @property string $code
 * @property string|null $program
 * @property int|null $capacity
 * @property string|null $start_date
 * @property string|null $end_date
 * @property string|null $schedule
 * @property string|null $description
 * @property CourseLevel $level
 * @property string|null $course_status
 * @property int|null $assigned_teacher_id
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Course extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'courses';

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'code',
        'program',
        'capacity',
        'start_date',
        'end_date',
        'schedule',
        'description',
        'level',
        'course_status',
        'assigned_teacher_id',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'level' => CourseLevel::class,
            'is_published' => 'boolean',
        ];
    }

    /**
     * Get the category of this course.
     *
     * @return BelongsTo<CourseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'category_id');
    }

    /**
     * Get enrollments for this course.
     *
     * @return HasMany<CourseEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class, 'course_id');
    }

    /**
     * Get teacher assignment pivot records.
     *
     * @return HasMany<TeacherAssignment, $this>
     */
    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class, 'course_id');
    }

    /**
     * Get all assigned teachers through teacher_assignments.
     *
     * @return BelongsToMany<User, $this>
     */
    public function assignedTeachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'teacher_assignments', 'course_id', 'teacher_id')
            ->withPivot(['role', 'status', 'assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    /**
     * Direct relationship for single assigned teacher fallback column.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_teacher_id');
    }

    /**
     * Helper to resolve the Lead Teacher for this course.
     */
    public function leadTeacher(): ?User
    {
        // 1. Search teacherAssignments relation
        if ($this->relationLoaded('teacherAssignments')) {
            $assignment = $this->teacherAssignments
                ->first(fn ($a) => in_array(strtolower($a->role ?? ''), ['lead', 'lead_teacher']) && strtolower($a->status ?? '') !== 'completed');
            if ($assignment && $assignment->teacher) {
                return $assignment->teacher;
            }
        }

        // 2. Search assignedTeachers relation
        if ($this->relationLoaded('assignedTeachers')) {
            $teacher = $this->assignedTeachers
                ->first(fn ($t) => in_array(strtolower($t->pivot->role ?? ''), ['lead', 'lead_teacher']));
            if ($teacher) {
                return $teacher;
            }
        }

        // 3. Fallback: single assigned_teacher_id
        if ($this->relationLoaded('assignedTeacher') && $this->assignedTeacher) {
            return $this->assignedTeacher;
        }

        return null;
    }

    /**
     * Accessor for lead teacher name or default display status.
     */
    public function getLeadTeacherNameAttribute(): string
    {
        $teacher = $this->leadTeacher();
        if ($teacher) {
            return $teacher->name;
        }

        // Check if any teacher is assigned at all
        if ($this->assignedTeachers->isNotEmpty() || $this->teacherAssignments->isNotEmpty()) {
            $first = $this->assignedTeachers->first() ?? $this->teacherAssignments->first()?->teacher;
            if ($first) {
                return $first->name;
            }
        }

        return 'No teacher assigned';
    }
}
