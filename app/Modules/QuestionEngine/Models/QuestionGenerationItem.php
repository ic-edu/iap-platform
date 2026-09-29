<?php

namespace App\Modules\QuestionEngine\Models;

use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\GenerationErrorCode;
use App\Modules\QuestionEngine\Enums\GenerationItemStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $generation_batch_id
 * @property int $slot_sequence
 * @property string|null $slot_fingerprint
 * @property AssessmentFamily $assessment_family
 * @property string|null $assessment_standard_id
 * @property string|null $standard_version
 * @property string|null $section
 * @property int|null $part_number
 * @property string|null $task_type
 * @property string|null $claim
 * @property string|null $skill
 * @property string|null $construct
 * @property string|null $proficiency_target
 * @property string|null $difficulty
 * @property string|null $domain
 * @property string|null $context
 * @property GenerationItemStatus $status
 * @property int $attempt_count
 * @property string|null $last_error_code
 * @property string|null $last_error_message
 * @property array|null $prompt_payload
 * @property array|null $provider_request_metadata
 * @property array|null $raw_output
 * @property array|null $normalized_output
 * @property array|null $validation_result
 * @property string|null $question_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class QuestionGenerationItem extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'question_generation_items';

    protected $fillable = [
        'generation_batch_id',
        'slot_sequence',
        'slot_fingerprint',
        'assessment_family',
        'assessment_standard_id',
        'standard_version',
        'section',
        'part_number',
        'task_type',
        'claim',
        'skill',
        'construct',
        'proficiency_target',
        'difficulty',
        'domain',
        'context',
        'status',
        'attempt_count',
        'last_error_code',
        'last_error_message',
        'prompt_payload',
        'provider_request_metadata',
        'raw_output',
        'normalized_output',
        'validation_result',
        'question_id',
    ];

    protected function casts(): array
    {
        return [
            'assessment_family' => AssessmentFamily::class,
            'status' => GenerationItemStatus::class,
            'slot_sequence' => 'integer',
            'part_number' => 'integer',
            'attempt_count' => 'integer',
            'prompt_payload' => 'array',
            'provider_request_metadata' => 'array',
            'raw_output' => 'array',
            'normalized_output' => 'array',
            'validation_result' => 'array',
        ];
    }

    /**
     * @return BelongsTo<QuestionGenerationBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(QuestionGenerationBatch::class, 'generation_batch_id');
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    /**
     * @return BelongsTo<AssessmentStandard, $this>
     */
    public function assessmentStandard(): BelongsTo
    {
        return $this->belongsTo(AssessmentStandard::class, 'assessment_standard_id');
    }

    /**
     * Mark item as processing.
     */
    public function markProcessing(): self
    {
        $this->status = GenerationItemStatus::Processing;
        $this->attempt_count++;
        $this->last_error_code = null;
        $this->last_error_message = null;

        return $this;
    }

    /**
     * Mark item failure with error code.
     */
    public function markFailed(GenerationErrorCode|string $errorCode, string $errorMessage): self
    {
        $this->status = GenerationItemStatus::Failed;
        $this->last_error_code = $errorCode instanceof GenerationErrorCode ? $errorCode->value : $errorCode;
        $this->last_error_message = $errorMessage;

        return $this;
    }

    /**
     * Mark item validation failed.
     */
    public function markValidationFailed(array $validationResult, ?string $message = null): self
    {
        $this->status = GenerationItemStatus::ValidationFailed;
        $this->validation_result = $validationResult;
        $this->last_error_code = GenerationErrorCode::QualityGateFailed->value;
        $this->last_error_message = $message ?? 'Quality gate validation failed.';

        return $this;
    }

    /**
     * Mark item validated.
     */
    public function markValidated(array $validationResult): self
    {
        $this->status = GenerationItemStatus::Validated;
        $this->validation_result = $validationResult;

        return $this;
    }

    /**
     * Mark item materialized into question.
     */
    public function markMaterialized(string $questionId): self
    {
        $this->status = GenerationItemStatus::Materialized;
        $this->question_id = $questionId;

        return $this;
    }

    /**
     * Determine if this item is eligible for another generation attempt.
     */
    public function isEligibleForRetry(int $maxAttempts = 3): bool
    {
        // 1. Quality failures and non-failed states are strictly ineligible
        if ($this->status !== GenerationItemStatus::Failed) {
            return false;
        }

        // 2. Budget enforcement
        if ($this->attempt_count >= $maxAttempts) {
            return false;
        }

        // 3. Explicit retryability recorded in raw_output
        if (isset($this->raw_output['retryable'])) {
            return (bool) $this->raw_output['retryable'];
        }

        // 4. Fallback on error code if retryable flag was not explicitly recorded
        if (!empty($this->last_error_code)) {
            $code = GenerationErrorCode::tryFrom($this->last_error_code);

            return $code ? $code->isRetryable() : false;
        }

        return false;
    }

    /**
     * Get operational telemetry including attempt history and latest execution metrics.
     *
     * @return array<string, mixed>
     */
    public function getOperationalTelemetry(): array
    {
        $raw = is_array($this->raw_output) ? $this->raw_output : [];

        return [
            'attempt_count' => $this->attempt_count,
            'status' => $this->status->value,
            'is_success' => ($this->status !== GenerationItemStatus::Failed && $this->status !== GenerationItemStatus::ValidationFailed) && (bool) ($raw['is_success'] ?? false),
            'provider_name' => $raw['provider_name'] ?? null,
            'model_name' => $raw['model_name'] ?? null,
            'latency_ms' => $raw['latency_ms'] ?? 0,
            'token_usage' => $raw['token_usage'] ?? [],
            'error_code' => $this->last_error_code ?? ($raw['error_code'] ?? null),
            'error_message' => $this->last_error_message ?? ($raw['error_message'] ?? null),
            'retryable' => $raw['retryable'] ?? false,
            'status_code' => $raw['status_code'] ?? null,
            'prompt_hash' => $raw['prompt_hash'] ?? ($this->prompt_payload['prompt_hash'] ?? null),
            'attempts' => $raw['attempts'] ?? [],
        ];
    }
}
