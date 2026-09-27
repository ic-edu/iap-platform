<?php

namespace App\Modules\Assessment\Models;

use App\Models\User;
use App\Modules\Assessment\Engines\ResultEngine;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\EvaluationStatus;
use App\Modules\Assessment\Enums\ResultReleaseStatus;
use App\Modules\Certificate\Models\Certificate;
use Illuminate\Database\Eloquent\Builder;
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
 * @property string|null $assignment_id
 * @property int $attempt_number
 * @property bool $is_final
 * @property string|null $decision_status
 * @property ResultReleaseStatus|null $result_release_status
 * @property Carbon|null $result_release_at
 * @property Carbon|null $result_released_at
 * @property int|null $result_released_by
 * @property Carbon|null $started_at
 * @property Carbon|null $submitted_at
 * @property float|null $total_score
 * @property array<string, mixed>|null $section_scores
 * @property AttemptStatus $status
 * @property EvaluationStatus $evaluation_status
 * @property string|null $current_question_id
 * @property array<int, string>|null $flagged_questions
 * @property array<int, string>|null $review_later_questions
 * @property int $violations_count
 * @property string|null $seed
 * @property Carbon|null $updated_at
 * @property User|null $user
 * @property User|null $resultReleasedBy
 * @property Test|null $test
 * @property CandidateTestAssignment|null $assignment
 * @property Certificate|null $certificate
 * @property array<string, mixed> $result_summary
 */
class Attempt extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'attempts';

    protected $attributes = [
        'evaluation_status' => 'not_required',
        'attempt_number' => 1,
        'is_final' => false,
    ];

    protected $fillable = [
        'test_id',
        'user_id',
        'assignment_id',
        'attempt_number',
        'is_final',
        'decision_status',
        'result_release_status',
        'result_release_at',
        'result_released_at',
        'result_released_by',
        'started_at',
        'submitted_at',
        'total_score',
        'section_scores',
        'status',
        'evaluation_status',
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
            'attempt_number' => 'integer',
            'is_final' => 'boolean',
            'section_scores' => 'array',
            'flagged_questions' => 'array',
            'review_later_questions' => 'array',
            'violations_count' => 'integer',
            'status' => AttemptStatus::class,
            'evaluation_status' => EvaluationStatus::class,
            'result_release_status' => ResultReleaseStatus::class,
            'result_release_at' => 'datetime',
            'result_released_at' => 'datetime',
            'result_released_by' => 'integer',
        ];
    }

    /**
     * Check if attempt is pending human evaluation.
     */
    public function isPendingEvaluation(): bool
    {
        return ($this->evaluation_status ?? EvaluationStatus::NotRequired) === EvaluationStatus::PendingEvaluation;
    }

    /**
     * Check if attempt evaluation is not required (e.g. automatic).
     */
    public function isEvaluationNotRequired(): bool
    {
        return ($this->evaluation_status ?? EvaluationStatus::NotRequired) === EvaluationStatus::NotRequired;
    }

    /**
     * Check if attempt has been fully evaluated.
     */
    public function isEvaluated(): bool
    {
        return in_array($this->evaluation_status, [EvaluationStatus::Evaluated, EvaluationStatus::Moderated, EvaluationStatus::NotRequired], true);
    }

    /**
     * Check if attempt passed according to ResultEngine evaluation.
     */
    public function isPassed(): bool
    {
        return (bool) ($this->result_summary['is_passed'] ?? false);
    }

    /**
     * Check if attempt is in a completed terminal state (Submitted or Expired).
     */
    public function isCompleted(): bool
    {
        if ($this->status instanceof AttemptStatus) {
            return $this->status->isCompleted();
        }

        $statusValue = (string) $this->status;

        return in_array($statusValue, [AttemptStatus::Submitted->value, AttemptStatus::Expired->value, 'submitted', 'expired'], true);
    }

    /**
     * Scope a query to only include completed attempts (Submitted and Expired).
     *
     * @param  Builder<Attempt>  $query
     * @return Builder<Attempt>
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', AttemptStatus::completedValues());
    }

    /**
     * Check if candidate is authorized to view item-level detailed question review.
     * Real / Mock Tests NEVER expose question-level review at any lifecycle state.
     */
    public function canCandidateViewDetailedQuestionReview(): bool
    {
        $test = $this->relationLoaded('test') && $this->test ? $this->test : $this->test()->first();
        if (!$test) {
            return false;
        }

        if ($test->isRealTest()) {
            return false;
        }

        if ($test->isSimulator()) {
            return $this->isCompleted();
        }

        return false;
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
     * Check if a question belongs to this attempt's test.
     */
    public function hasQuestion(string $questionId): bool
    {
        if ($this->relationLoaded('test') && $this->test) {
            return $this->test->hasQuestion($questionId);
        }

        return TestQuestion::whereHas('section', fn ($q) => $q->where('test_id', $this->test_id))
            ->where('question_id', $questionId)
            ->exists();
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
     * Get candidate test assignment.
     *
     * @return BelongsTo<CandidateTestAssignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(CandidateTestAssignment::class, 'assignment_id');
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

    /**
     * Get user who authorized/released the results.
     *
     * @return BelongsTo<User, $this>
     */
    public function resultReleasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'result_released_by');
    }

    /**
     * Check if attempt result is released and candidate-accessible.
     */
    public function isResultReleased(): bool
    {
        if ($this->result_release_status instanceof ResultReleaseStatus) {
            return $this->result_release_status->isReleased();
        }

        return $this->result_release_status === 'released' || $this->result_released_at !== null;
    }

    /**
     * Check if attempt result is still in processing state.
     */
    public function isResultProcessing(): bool
    {
        if ($this->result_release_status instanceof ResultReleaseStatus) {
            return $this->result_release_status->isProcessing();
        }

        return $this->result_release_status === 'processing';
    }

    /**
     * Check if attempt result is ready for release.
     */
    public function isResultReady(): bool
    {
        if ($this->result_release_status instanceof ResultReleaseStatus) {
            return $this->result_release_status->isReady();
        }

        return $this->result_release_status === 'ready';
    }

    /**
     * Check if attempt result can be released (all invariants satisfied: completed, non-simulator, evaluated, elapsed, and unreleased).
     */
    public function canResultBeReleased(): bool
    {
        if ($this->isResultReleased()) {
            return false;
        }

        if (!$this->isCompleted()) {
            return false;
        }

        if ($this->isPendingEvaluation()) {
            return false;
        }

        $test = $this->relationLoaded('test') && $this->test ? $this->test : $this->test()->first();
        if (!$test || $test->isSimulator()) {
            return false;
        }

        if ($this->result_release_at === null) {
            return false;
        }

        return now()->greaterThanOrEqualTo($this->result_release_at);
    }

    /**
     * Get the completion label for display ("Submitted at" vs "Time expired at" vs "Cancelled at").
     */
    public function getCompletionLabel(): string
    {
        $statusValue = is_object($this->status) ? $this->status->value : (string) $this->status;

        if ($statusValue === AttemptStatus::Expired->value || $statusValue === 'expired') {
            return 'Time expired at';
        }

        if ($statusValue === AttemptStatus::Cancelled->value || $statusValue === 'cancelled') {
            return 'Cancelled at';
        }

        return 'Submitted at';
    }

    /**
     * Get canonical completion timestamp for display.
     * For expired attempts, derives the canonical deadline (started_at + duration_minutes).
     * For cancelled attempts, returns updated_at.
     * For submitted attempts, returns submitted_at (or updated_at fallback).
     */
    public function getCanonicalCompletionTimestamp(): ?Carbon
    {
        $statusValue = is_object($this->status) ? $this->status->value : (string) $this->status;

        if ($statusValue === AttemptStatus::Expired->value || $statusValue === 'expired') {
            $test = $this->relationLoaded('test') && $this->test ? $this->test : $this->test()->first();
            if ($this->started_at && $test && $test->duration_minutes > 0) {
                return $this->started_at->copy()->addMinutes($test->duration_minutes);
            }
        }

        if ($statusValue === AttemptStatus::Cancelled->value || $statusValue === 'cancelled') {
            return $this->updated_at;
        }

        return $this->submitted_at ?? $this->updated_at;
    }

    /**
     * Get candidate-facing formatted completion string (e.g. "Time expired at 26 Sep 2026, 15:45").
     */
    public function getFormattedCompletionDisplay(): string
    {
        $label = $this->getCompletionLabel();
        $timestamp = $this->getCanonicalCompletionTimestamp();
        $formattedTime = $timestamp ? $timestamp->format('d M Y, H:i') : 'N/A';

        return "{$label} {$formattedTime}";
    }
}
