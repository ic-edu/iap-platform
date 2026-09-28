<?php

namespace App\Modules\QuestionEngine\Models;

use App\Models\User;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\GenerationBatchStatus;
use App\Modules\QuestionEngine\Enums\GenerationItemStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $question_bank_id
 * @property AssessmentFamily $assessment_family
 * @property string $assessment_standard_id
 * @property string $standard_version
 * @property string $planner_type
 * @property string $planner_strategy_version
 * @property string $plan_fingerprint
 * @property GenerationBatchStatus $status
 * @property int|null $requested_by
 * @property string $idempotency_key
 * @property string $prompt_contract_version
 * @property int $total_slots
 * @property int $pending_slots
 * @property int $processing_slots
 * @property int $generated_slots
 * @property int $validated_slots
 * @property int $failed_slots
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $failed_at
 * @property array|null $generation_config
 * @property array|null $plan_snapshot
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class QuestionGenerationBatch extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'question_generation_batches';

    protected $fillable = [
        'question_bank_id',
        'assessment_family',
        'assessment_standard_id',
        'standard_version',
        'planner_type',
        'planner_strategy_version',
        'plan_fingerprint',
        'status',
        'requested_by',
        'idempotency_key',
        'prompt_contract_version',
        'total_slots',
        'pending_slots',
        'processing_slots',
        'generated_slots',
        'validated_slots',
        'failed_slots',
        'started_at',
        'completed_at',
        'failed_at',
        'generation_config',
        'plan_snapshot',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'assessment_family' => AssessmentFamily::class,
            'status' => GenerationBatchStatus::class,
            'total_slots' => 'integer',
            'pending_slots' => 'integer',
            'processing_slots' => 'integer',
            'generated_slots' => 'integer',
            'validated_slots' => 'integer',
            'failed_slots' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'generation_config' => 'array',
            'plan_snapshot' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * @return HasMany<QuestionGenerationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuestionGenerationItem::class, 'generation_batch_id')->orderBy('slot_sequence');
    }

    /**
     * @return BelongsTo<QuestionBank, $this>
     */
    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    /**
     * @return BelongsTo<AssessmentStandard, $this>
     */
    public function assessmentStandard(): BelongsTo
    {
        return $this->belongsTo(AssessmentStandard::class, 'assessment_standard_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Recalculate slot status counters from child items.
     */
    public function recalculateSlotCounts(): self
    {
        $counts = $this->items()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $this->total_slots = $this->items()->count();
        $this->pending_slots = ($counts[GenerationItemStatus::Pending->value] ?? 0) + ($counts[GenerationItemStatus::Ready->value] ?? 0);
        $this->processing_slots = $counts[GenerationItemStatus::Processing->value] ?? 0;
        $this->generated_slots = $counts[GenerationItemStatus::Generated->value] ?? 0;
        $this->validated_slots = ($counts[GenerationItemStatus::Validated->value] ?? 0) + ($counts[GenerationItemStatus::Materialized->value] ?? 0);
        $this->failed_slots = ($counts[GenerationItemStatus::Failed->value] ?? 0) + ($counts[GenerationItemStatus::ValidationFailed->value] ?? 0);

        return $this;
    }

    /**
     * Check if batch is fully materialized or final.
     */
    public function isComplete(): bool
    {
        return $this->status === GenerationBatchStatus::Completed;
    }
}
