<?php

namespace App\Modules\Assessment\Models;

use App\Models\User;
use App\Modules\Assessment\Enums\ScoringMethod;
use App\Modules\QuestionBank\Enums\TestType;
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
 * @property int $duration_minutes
 * @property int $pass_score
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
        'scoring_method'  => 'automatic',
        'assessment_mode' => 'simulator',
    ];

    protected $fillable = [
        'title',
        'slug',
        'test_type',
        'assessment_mode',
        'duration_minutes',
        'pass_score',
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
            'test_type'         => TestType::class,
            'assessment_mode'   => \App\Modules\Assessment\Enums\AssessmentMode::class,
            'scoring_method'    => ScoringMethod::class,
            'duration_minutes'  => 'integer',
            'pass_score'        => 'integer',
            'shuffle_questions' => 'boolean',
            'shuffle_choices'   => 'boolean',
            'is_published'      => 'boolean',
        ];
    }

    /**
     * Check if test is a practice simulator.
     */
    public function isSimulator(): bool
    {
        return ($this->assessment_mode ?? \App\Modules\Assessment\Enums\AssessmentMode::Simulator) === \App\Modules\Assessment\Enums\AssessmentMode::Simulator;
    }

    /**
     * Check if test is an official real test.
     */
    public function isRealTest(): bool
    {
        return $this->assessment_mode === \App\Modules\Assessment\Enums\AssessmentMode::RealTest;
    }

    /**
     * Get Assessment Mode Policy instance.
     */
    public function policy(): \App\Modules\Assessment\Policies\AssessmentModePolicy
    {
        return \App\Modules\Assessment\Policies\AssessmentModePolicy::for($this);
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
     * Check if test is approved.
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
        return $this->belongsTo(\App\Models\AssessmentRequest::class, 'assessment_request_id');
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

    /**
     * Get candidate test assignments.
     *
     * @return HasMany<\App\Modules\Assessment\Models\CandidateTestAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(\App\Modules\Assessment\Models\CandidateTestAssignment::class, 'test_id');
    }

    /**
     * Get associated shared audio groups.
     *
     * @return HasMany<\App\Modules\QuestionBank\Models\AudioGroup, $this>
     */
    public function audioGroups(): HasMany
    {
        return $this->hasMany(\App\Modules\QuestionBank\Models\AudioGroup::class, 'test_id');
    }

    /**
     * Get associated shared passage groups (Part 6 & Part 7).
     *
     * @return HasMany<\App\Modules\QuestionBank\Models\PassageGroup, $this>
     */
    public function passageGroups(): HasMany
    {
        return $this->hasMany(\App\Modules\QuestionBank\Models\PassageGroup::class, 'test_id');
    }
}
