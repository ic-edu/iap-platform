<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionEngine\Contracts\QuestionGenerationProvider;
use App\Modules\QuestionEngine\DTO\GenerationProviderRequest;
use App\Modules\QuestionEngine\DTO\ToeflGenerationPlan;
use App\Modules\QuestionEngine\DTO\ToeicGenerationPlan;
use App\Modules\QuestionEngine\Enums\GenerationBatchStatus;
use App\Modules\QuestionEngine\Enums\GenerationErrorCode;
use App\Modules\QuestionEngine\Enums\GenerationItemStatus;
use App\Modules\QuestionEngine\Models\QuestionGenerationBatch;
use App\Modules\QuestionEngine\Models\QuestionGenerationItem;
use App\Modules\QuestionEngine\Providers\NullGenerationProvider;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QuestionGenerationOrchestrator
{
    public function __construct(
        protected GenerationBatchFactory $batchFactory,
        protected QuestionPromptComposerResolver $composerResolver,
        protected GeneratedQuestionNormalizer $normalizer,
        protected GeneratedQuestionQualityGate $qualityGate,
        protected GeneratedQuestionMaterializer $materializer,
        protected ?QuestionGenerationProvider $defaultProvider = null,
        protected ?GeneratedAnswerChoicePositioner $positioner = null
    ) {
        $this->positioner ??= new GeneratedAnswerChoicePositioner;

        if ($this->defaultProvider === null) {
            if (app()->bound(QuestionGenerationProvider::class)) {
                $this->defaultProvider = app(QuestionGenerationProvider::class);
            } else {
                $this->defaultProvider = new NullGenerationProvider;
            }
        }
    }

    /**
     * Create a generation batch from a blueprint plan.
     *
     * @param  array<string, mixed>  $options
     */
    public function createBatchFromPlan(ToeicGenerationPlan|ToeflGenerationPlan $plan, array $options = []): QuestionGenerationBatch
    {
        return $this->batchFactory->createFromPlan($plan, $options);
    }

    /**
     * Process an entire batch sequentially or up to limits.
     *
     * @param  array<string, mixed>  $options
     */
    public function processBatch(
        QuestionGenerationBatch $batch,
        ?QuestionGenerationProvider $provider = null,
        array $options = []
    ): QuestionGenerationBatch {
        $activeProvider = $provider ?? $this->defaultProvider;

        // If batch is already complete or cancelled
        if ($batch->status === GenerationBatchStatus::Completed || $batch->status === GenerationBatchStatus::Cancelled) {
            return $batch;
        }

        $batch->status = GenerationBatchStatus::Processing;
        if (!$batch->started_at) {
            $batch->started_at = Carbon::now();
        }
        $batch->save();

        $items = $batch->items()
            ->whereIn('status', [GenerationItemStatus::Pending, GenerationItemStatus::Ready])
            ->orderBy('slot_sequence')
            ->get();

        foreach ($items as $item) {
            $this->processItem($item, $activeProvider, $options);
        }

        return $this->finalizeBatchState($batch->fresh(['items']));
    }

    /**
     * Process a single generation item through prompt composition, generation, normalization, quality gating, and materialization.
     *
     * @param  array<string, mixed>  $options
     */
    public function processItem(
        QuestionGenerationItem $item,
        ?QuestionGenerationProvider $provider = null,
        array $options = []
    ): QuestionGenerationItem {
        $activeProvider = $provider ?? $this->defaultProvider;
        $maxAttempts = (int) ($options['max_attempts'] ?? 3);

        // Atomic transition from pending/ready to processing
        $claimed = DB::transaction(function () use ($item, $maxAttempts) {
            /** @var QuestionGenerationItem|null $fresh */
            $fresh = QuestionGenerationItem::where('id', $item->id)
                ->lockForUpdate()
                ->first();

            if (!$fresh || !$fresh->status->canAttempt()) {
                return false;
            }

            if ($fresh->attempt_count > 0 && !$fresh->isEligibleForRetry($maxAttempts)) {
                return false;
            }

            $fresh->markProcessing();
            $fresh->save();

            return true;
        });

        if (!$claimed) {
            return $item->fresh();
        }

        $item->refresh();

        try {
            // 1. Compose Prompt
            $composer = $this->composerResolver->resolve($item->assessment_family);
            $promptComposition = $composer->compose($item, $options);
            $item->prompt_payload = $promptComposition->toArray();

            // 2. Build Provider Request
            $providerRequest = new GenerationProviderRequest(
                batchId: $item->generation_batch_id,
                itemId: $item->id,
                slotSequence: $item->slot_sequence,
                promptComposition: $promptComposition,
                generationParameters: $options['generation_parameters'] ?? [],
                metadata: ['provider' => $activeProvider->getProviderName()]
            );

            $item->provider_request_metadata = $providerRequest->toArray();

            // 3. Execute Provider Generation
            $providerResponse = $activeProvider->generate($providerRequest);

            $isRetryable = isset($providerResponse->metadata['retryable'])
                ? (bool) $providerResponse->metadata['retryable']
                : ($providerResponse->errorCode?->isRetryable() ?? false);

            $attemptRecord = [
                'attempt_number' => $item->attempt_count,
                'stage' => 'provider_generation',
                'is_success' => $providerResponse->isSuccess,
                'provider_name' => $providerResponse->providerName ?? $activeProvider->getProviderName(),
                'model_name' => $providerResponse->modelName ?? ($providerResponse->metadata['model'] ?? null),
                'latency_ms' => $providerResponse->latencyMs,
                'token_usage' => $providerResponse->tokenUsage,
                'error_code' => $providerResponse->errorCode?->value,
                'error_message' => $providerResponse->errorMessage,
                'retryable' => $isRetryable,
                'status_code' => $providerResponse->metadata['status_code'] ?? null,
                'prompt_hash' => $providerResponse->metadata['prompt_hash'] ?? $promptComposition->computePromptHash(),
                'recorded_at' => Carbon::now()->toIso8601String(),
            ];

            $rawOutput = is_array($item->raw_output) ? $item->raw_output : [];
            $attempts = is_array($rawOutput['attempts'] ?? null) ? $rawOutput['attempts'] : [];
            $attempts[] = $attemptRecord;

            if (!$providerResponse->isSuccess) {
                $item->raw_output = [
                    'is_success' => false,
                    'provider_name' => $providerResponse->providerName ?? $activeProvider->getProviderName(),
                    'model_name' => $providerResponse->modelName ?? ($providerResponse->metadata['model'] ?? null),
                    'latency_ms' => $providerResponse->latencyMs,
                    'token_usage' => $providerResponse->tokenUsage,
                    'error_code' => $providerResponse->errorCode?->value,
                    'error_message' => $providerResponse->errorMessage,
                    'retryable' => $isRetryable,
                    'status_code' => $providerResponse->metadata['status_code'] ?? null,
                    'prompt_hash' => $providerResponse->metadata['prompt_hash'] ?? $promptComposition->computePromptHash(),
                    'attempts' => $attempts,
                ];

                $item->markFailed(
                    errorCode: $providerResponse->errorCode ?? GenerationErrorCode::ProviderError,
                    errorMessage: $providerResponse->errorMessage ?? 'Provider generation failed.'
                );
                $item->save();

                return $item;
            }

            $item->raw_output = [
                'is_success' => true,
                'provider_name' => $providerResponse->providerName,
                'model_name' => $providerResponse->modelName,
                'latency_ms' => $providerResponse->latencyMs,
                'token_usage' => $providerResponse->tokenUsage,
                'raw_content' => $providerResponse->rawContent,
                'parsed_payload' => $providerResponse->parsedPayload,
                'retryable' => false,
                'prompt_hash' => $providerResponse->metadata['prompt_hash'] ?? $promptComposition->computePromptHash(),
                'attempts' => $attempts,
            ];
            $item->status = GenerationItemStatus::Generated;

            // 4. Normalize Candidate & Apply Deterministic System-Side Answer Positioning
            try {
                $candidate = $this->normalizer->normalize($providerResponse, $item);
                $candidate = $this->positioner->reposition($candidate, $item);
                $item->normalized_output = $candidate->toArray();
            } catch (Exception $e) {
                $rawOutput = is_array($item->raw_output) ? $item->raw_output : [];
                $normAttempts = is_array($rawOutput['attempts'] ?? null) ? $rawOutput['attempts'] : [];
                $normAttempts[] = [
                    'attempt_number' => $item->attempt_count,
                    'stage' => 'normalization',
                    'is_success' => false,
                    'error_code' => GenerationErrorCode::NormalizationFailed->value,
                    'error_message' => "Candidate normalization error: {$e->getMessage()}",
                    'retryable' => false,
                    'recorded_at' => Carbon::now()->toIso8601String(),
                ];

                $rawOutput['attempts'] = $normAttempts;
                $rawOutput['retryable'] = false;
                $rawOutput['is_success'] = false;
                $item->raw_output = $rawOutput;

                $item->markFailed(
                    errorCode: GenerationErrorCode::NormalizationFailed,
                    errorMessage: "Candidate normalization error: {$e->getMessage()}"
                );
                $item->save();

                return $item;
            }

            // 5. Quality Gate
            $validationResult = $this->qualityGate->validate($candidate, $item);

            if (!$validationResult->isValid) {
                $rawOutput = is_array($item->raw_output) ? $item->raw_output : [];
                $qgAttempts = is_array($rawOutput['attempts'] ?? null) ? $rawOutput['attempts'] : [];
                $qgAttempts[] = [
                    'attempt_number' => $item->attempt_count,
                    'stage' => 'quality_gate',
                    'is_success' => false,
                    'error_code' => GenerationErrorCode::QualityGateFailed->value,
                    'error_message' => 'Quality gate validation failed: '.json_encode($validationResult->violations),
                    'retryable' => false,
                    'recorded_at' => Carbon::now()->toIso8601String(),
                ];

                $rawOutput['attempts'] = $qgAttempts;
                $rawOutput['retryable'] = false;
                $rawOutput['is_success'] = false;
                $item->raw_output = $rawOutput;

                $item->markValidationFailed(
                    validationResult: $validationResult->toArray(),
                    message: 'Quality gate validation failed: '.json_encode($validationResult->violations)
                );
                $item->save();

                return $item;
            }

            $item->markValidated($validationResult->toArray());
            $item->save();

            // 6. Materialize into Question Draft
            try {
                $this->materializer->materialize($item, $candidate, [
                    'provider_name' => $providerResponse->providerName,
                    'latency_ms' => $providerResponse->latencyMs,
                ]);
                $item->refresh();
            } catch (Exception $e) {
                $rawOutput = is_array($item->raw_output) ? $item->raw_output : [];
                $matAttempts = is_array($rawOutput['attempts'] ?? null) ? $rawOutput['attempts'] : [];
                $matAttempts[] = [
                    'attempt_number' => $item->attempt_count,
                    'stage' => 'materialization',
                    'is_success' => false,
                    'error_code' => GenerationErrorCode::MaterializationFailed->value,
                    'error_message' => "Question materialization error: {$e->getMessage()}",
                    'retryable' => false,
                    'recorded_at' => Carbon::now()->toIso8601String(),
                ];

                $rawOutput['attempts'] = $matAttempts;
                $rawOutput['retryable'] = false;
                $rawOutput['is_success'] = false;
                $item->raw_output = $rawOutput;

                $item->markFailed(
                    errorCode: GenerationErrorCode::MaterializationFailed,
                    errorMessage: "Question materialization error: {$e->getMessage()}"
                );
                $item->save();

                return $item;
            }

            return $item;
        } catch (Exception $e) {
            $rawOutput = is_array($item->raw_output) ? $item->raw_output : [];
            $unexpAttempts = is_array($rawOutput['attempts'] ?? null) ? $rawOutput['attempts'] : [];
            $unexpAttempts[] = [
                'attempt_number' => $item->attempt_count,
                'stage' => 'orchestration',
                'is_success' => false,
                'error_code' => GenerationErrorCode::ProviderError->value,
                'error_message' => "Unexpected orchestration error: {$e->getMessage()}",
                'retryable' => false,
                'recorded_at' => Carbon::now()->toIso8601String(),
            ];

            $rawOutput['attempts'] = $unexpAttempts;
            $rawOutput['retryable'] = false;
            $rawOutput['is_success'] = false;
            $item->raw_output = $rawOutput;

            $item->markFailed(
                errorCode: GenerationErrorCode::ProviderError,
                errorMessage: "Unexpected orchestration error: {$e->getMessage()}"
            );
            $item->save();

            return $item;
        }
    }

    /**
     * Retry failed items in a batch up to maxAttempts, strictly enforcing retry eligibility.
     */
    public function retryFailedItems(
        QuestionGenerationBatch $batch,
        ?QuestionGenerationProvider $provider = null,
        int $maxAttempts = 3,
        array $options = []
    ): QuestionGenerationBatch {
        $activeProvider = $provider ?? $this->defaultProvider;
        $effectiveOptions = array_merge($options, ['max_attempts' => $maxAttempts]);

        $failedItems = $batch->items()
            ->where('status', GenerationItemStatus::Failed)
            ->where('attempt_count', '<', $maxAttempts)
            ->get();

        foreach ($failedItems as $item) {
            if ($item->isEligibleForRetry($maxAttempts)) {
                $this->processItem($item, $activeProvider, $effectiveOptions);
            }
        }

        return $this->finalizeBatchState($batch->fresh(['items']));
    }

    /**
     * Cancel an active or draft batch.
     */
    public function cancelBatch(QuestionGenerationBatch $batch, ?string $reason = null): QuestionGenerationBatch
    {
        if (!$batch->status->canCancel()) {
            throw new InvalidArgumentException("Batch in status [{$batch->status->value}] cannot be cancelled.");
        }

        DB::transaction(function () use ($batch, $reason) {
            $batch->status = GenerationBatchStatus::Cancelled;
            if ($reason) {
                $meta = $batch->metadata ?? [];
                $meta['cancellation_reason'] = $reason;
                $batch->metadata = $meta;
            }
            $batch->save();

            $batch->items()
                ->whereIn('status', [GenerationItemStatus::Pending, GenerationItemStatus::Ready, GenerationItemStatus::Processing])
                ->update(['status' => GenerationItemStatus::Cancelled]);
        });

        return $this->finalizeBatchState($batch->fresh(['items']));
    }

    /**
     * Recalculate status counters and set final status if applicable.
     */
    protected function finalizeBatchState(QuestionGenerationBatch $batch): QuestionGenerationBatch
    {
        $batch->recalculateSlotCounts();

        $items = $batch->items;
        $total = $items->count();
        $materializedCount = $items->where('status', GenerationItemStatus::Materialized)->count();
        $failedCount = $items->whereIn('status', [GenerationItemStatus::Failed, GenerationItemStatus::ValidationFailed])->count();
        $pendingCount = $items->whereIn('status', [GenerationItemStatus::Pending, GenerationItemStatus::Ready, GenerationItemStatus::Processing])->count();
        $cancelledCount = $items->where('status', GenerationItemStatus::Cancelled)->count();

        if ($batch->status === GenerationBatchStatus::Cancelled) {
            $batch->save();

            return $batch;
        }

        if ($pendingCount === 0) {
            if ($materializedCount === $total && $total > 0) {
                $batch->status = GenerationBatchStatus::Completed;
                $batch->completed_at = Carbon::now();
            } elseif ($materializedCount > 0 && ($failedCount > 0 || $cancelledCount > 0)) {
                $batch->status = GenerationBatchStatus::PartiallyCompleted;
                $batch->completed_at = Carbon::now();
            } elseif ($failedCount === $total && $total > 0) {
                $batch->status = GenerationBatchStatus::Failed;
                $batch->failed_at = Carbon::now();
            }
        }

        $batch->save();

        return $batch;
    }
}
