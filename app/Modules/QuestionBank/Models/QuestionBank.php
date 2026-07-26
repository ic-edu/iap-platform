<?php

namespace App\Modules\QuestionBank\Models;

use App\Models\User;
use App\Modules\Academic\Models\CourseCategory;
use App\Modules\QuestionBank\Enums\TestType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $title
 * @property string $slug
 * @property string|null $category_id
 * @property int $created_by
 * @property TestType $test_type
 * @property string|null $description
 */
class QuestionBank extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'question_banks';

    protected $fillable = [
        'title',
        'slug',
        'category_id',
        'created_by',
        'test_type',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'test_type' => TestType::class,
        ];
    }

    /**
     * Get the category of this bank.
     *
     * @return BelongsTo<CourseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'category_id');
    }

    /**
     * Get the creator user.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get questions in this bank.
     *
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'question_bank_id');
    }
}
