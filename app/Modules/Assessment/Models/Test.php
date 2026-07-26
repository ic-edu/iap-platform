<?php

namespace App\Modules\Assessment\Models;

use App\Models\User;
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
 * @property TestType $test_type
 * @property int $duration_minutes
 * @property int $pass_score
 * @property bool $shuffle_questions
 * @property bool $shuffle_choices
 * @property bool $is_published
 * @property int $created_by
 */
class Test extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'tests';

    protected $fillable = [
        'title',
        'slug',
        'test_type',
        'duration_minutes',
        'pass_score',
        'shuffle_questions',
        'shuffle_choices',
        'is_published',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'test_type' => TestType::class,
            'duration_minutes' => 'integer',
            'pass_score' => 'integer',
            'shuffle_questions' => 'boolean',
            'shuffle_choices' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    /**
     * Get creator of test.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get test sections.
     *
     * @return HasMany<TestSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(TestSection::class, 'test_id')->orderBy('order');
    }

    /**
     * Get test attempts.
     *
     * @return HasMany<Attempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class, 'test_id');
    }
}
