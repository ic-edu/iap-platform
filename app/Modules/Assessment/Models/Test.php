<?php

namespace App\Modules\Assessment\Models;

use App\Models\AssessmentRequest;
use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\ResultReleaseMode;
use App\Modules\Assessment\Enums\ScoringMethod;
use App\Modules\Assessment\Policies\AssessmentModePolicy;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\AudioGroup;
use App\Modules\QuestionBank\Models\PassageGroup;
use App\Services\ToeicQuestionValidator;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $title
 * @property string $slug
 * @property TestType $test_type
 * @property AssessmentMode $assessment_mode
 * @property int $duration_minutes
 * @property int $pass_score
 * @property int $result_release_delay_hours
 * @property ResultReleaseMode|string $result_release_mode
 * @property ScoringMethod $scoring_method
 * @property bool $shuffle_questions
 * @property bool $shuffle_choices
 * @property bool $is_published
 * @property string $status
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Test extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'tests';

    protected $attributes = [
        'scoring_method' => 'automatic',
        'assessment_mode' => 'simulator',
        'result_release_delay_hours' => 24,
        'result_release_mode' => 'ra_controlled',
    ];

    protected $fillable = [
        'title',
        'slug',
        'test_type',
        'assessment_mode',
        'duration_minutes',
        'pass_score',
        'result_release_delay_hours',
        'result_release_mode',
        'scoring_method',
        'shuffle_questions',
        'shuffle_choices',
        'is_published',
        'status',
        'created_by',
        'assigned_to',
        'assessment_request_id',
        'instructions',
    ];

    protected function casts(): array
    {
        return [
            'test_type' => TestType::class,
            'assessment_mode' => AssessmentMode::class,
            'scoring_method' => ScoringMethod::class,
            'duration_minutes' => 'integer',
            'pass_score' => 'integer',
            'result_release_delay_hours' => 'integer',
            'result_release_mode' => ResultReleaseMode::class,
            'shuffle_questions' => 'boolean',
            'shuffle_choices' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    /**
     * Check if test is a practice simulator.
     */
    public function isSimulator(): bool
    {
        return ($this->assessment_mode ?? AssessmentMode::Simulator) === AssessmentMode::Simulator;
    }

    /**
     * Check if test is an official real test.
     */
    public function isRealTest(): bool
    {
        return $this->assessment_mode === AssessmentMode::RealTest;
    }

    /**
     * Check if test is a TOEIC assessment.
     */
    public function isToeic(): bool
    {
        return $this->test_type === TestType::Toeic || (is_object($this->test_type) && $this->test_type->value === 'toeic') || $this->test_type === 'toeic';
    }

    /**
     * Check whether this assessment test uses delayed result release policy.
     * Practice simulators are always instant (never delayed).
     */
    public function usesDelayedResultRelease(): bool
    {
        if ($this->isSimulator()) {
            return false;
        }

        return $this->isRealTest();
    }

    /**
     * Get configured result release delay in hours.
     * Practice simulators return 0 hours.
     */
    public function getResultReleaseDelayHours(): int
    {
        if ($this->isSimulator()) {
            return 0;
        }

        return (int) ($this->result_release_delay_hours ?? 24);
    }

    /**
     * Get configured result release mode string.
     * Practice simulators return 'automatic'.
     */
    public function getResultReleaseMode(): string
    {
        if ($this->isSimulator()) {
            return 'automatic';
        }

        if ($this->result_release_mode instanceof ResultReleaseMode) {
            return $this->result_release_mode->value;
        }

        return (string) ($this->result_release_mode ?? 'ra_controlled');
    }

    /**
     * Get user-facing formatted passing threshold.
     */
    public function getPassingThresholdDisplay(): string
    {
        if ($this->isSimulator()) {
            return '75% Accuracy';
        }

        return "{$this->pass_score} Points";
    }

    /**
     * Get Assessment Mode Policy instance.
     */
    public function policy(): AssessmentModePolicy
    {
        return AssessmentModePolicy::for($this);
    }

    /**
     * Check if test scoring is automatic.
     */
    public function isAutomatic(): bool
    {
        return ($this->scoring_method ?? ScoringMethod::Automatic) === ScoringMethod::Automatic;
    }

    /**
     * Check if test scoring requires human evaluation.
     */
    public function isHuman(): bool
    {
        return $this->scoring_method === ScoringMethod::Human;
    }

    /**
     * Check if test scoring is hybrid.
     */
    public function isHybrid(): bool
    {
        return $this->scoring_method === ScoringMethod::Hybrid;
    }

    /**
     * Check if test requires examiner evaluation.
     */
    public function requiresEvaluation(): bool
    {
        return in_array($this->scoring_method, [ScoringMethod::Human, ScoringMethod::Hybrid], true);
    }

    /**
     * Scope a query to only include canonically published tests.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')->where('is_published', true);
    }

    /**
     * Check if test is canonically published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published' && (bool) $this->is_published;
    }

    /**
     * Check if test is approved (governance approved, not operationally live).
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if test is pending approval.
     */
    public function isPendingApproval(): bool
    {
        return $this->status === 'pending_approval';
    }

    /**
     * Check if test is draft.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft' || empty($this->status);
    }

    /**
     * Check if test belongs to a standardized examination framework (e.g. TOEIC, TOEFL, IELTS).
     */
    public function isStandardizedTest(): bool
    {
        $type = is_object($this->test_type) ? $this->test_type->value : (string) $this->test_type;
        $normalizedType = strtolower(trim($type));

        if (in_array($normalizedType, ['toeic', 'toefl', 'ielts'], true)) {
            return true;
        }

        return ToeicQuestionValidator::isToeic($this);
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
     * Get assigned teacher of test.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get original assessment request.
     */
    public function assessmentRequest(): BelongsTo
    {
        return $this->belongsTo(AssessmentRequest::class, 'assessment_request_id');
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
     * Check if a question belongs to any section of this test.
     */
    public function hasQuestion(string $questionId): bool
    {
        if ($this->relationLoaded('sections')) {
            $allLoaded = $this->sections->every(fn ($s) => $s->relationLoaded('testQuestions'));
            if ($allLoaded) {
                return $this->sections->contains(fn ($s) => $s->testQuestions->contains('question_id', $questionId));
            }
        }

        return TestQuestion::whereHas('section', fn ($q) => $q->where('test_id', $this->id))
            ->where('question_id', $questionId)
            ->exists();
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

    /**
     * Get candidate test assignments.
     *
     * @return HasMany<CandidateTestAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(CandidateTestAssignment::class, 'test_id');
    }

    /**
     * Get associated shared audio groups.
     *
     * @return HasMany<AudioGroup, $this>
     */
    public function audioGroups(): HasMany
    {
        return $this->hasMany(AudioGroup::class, 'test_id');
    }

    /**
     * Get associated shared passage groups (Part 6 & Part 7).
     *
     * @return HasMany<PassageGroup, $this>
     */
    public function passageGroups(): HasMany
    {
        return $this->hasMany(PassageGroup::class, 'test_id');
    }
}
