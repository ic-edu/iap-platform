<?php

namespace App\Modules\Assessment\Models;

use App\Models\User;
use App\Modules\Assessment\Engines\ResultEngine;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Certificate\Models\Certificate;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $test_id
 * @property int $user_id
 * @property Carbon|null $started_at
 * @property Carbon|null $submitted_at
 * @property float|null $total_score
 * @property array<string, mixed>|null $section_scores
 * @property AttemptStatus $status
 * @property string|null $current_question_id
 * @property array<int, string>|null $flagged_questions
 * @property array<int, string>|null $review_later_questions
 * @property int $violations_count
 * @property string|null $seed
 * @property Carbon|null $updated_at
 * @property User|null $user
 * @property Test|null $test
 * @property Certificate|null $certificate
 * @property array<string, mixed> $result_summary
 */
class Attempt extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'attempts';

    protected $fillable = [
        'test_id',
        'user_id',
        'started_at',
        'submitted_at',
        'total_score',
        'section_scores',
        'status',
        'current_question_id',
        'flagged_questions',
        'review_later_questions',
        'violations_count',
        'seed',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'total_score' => 'float',
            'section_scores' => 'array',
            'flagged_questions' => 'array',
            'review_later_questions' => 'array',
            'violations_count' => 'integer',
            'status' => AttemptStatus::class,
        ];
    }

    /**
     * Get evaluated result summary from single source of truth (ResultEngine).
     *
     * @return array<string, mixed>
     */
    public function getResultSummaryAttribute(): array
    {
        return app(ResultEngine::class)->generateResult($this);
    }

    /**
     * Get test.
     *
     * @return BelongsTo<Test, $this>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class, 'test_id');
    }

    /**
     * Get student user.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get answers given in this attempt.
     *
     * @return HasMany<Answer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class, 'attempt_id');
    }

    /**
     * Get certificate issued for this attempt.
     *
     * @return HasOne<Certificate, $this>
     */
    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class, 'attempt_id');
    }
}
