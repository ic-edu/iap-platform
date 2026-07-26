<?php

namespace App\Modules\Academic\Models;

use App\Modules\Academic\Enums\CourseLevel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $category_id
 * @property string $title
 * @property string $slug
 * @property string $code
 * @property string|null $description
 * @property CourseLevel $level
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
        'description',
        'level',
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
}
