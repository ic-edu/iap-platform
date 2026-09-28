<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionEngine\DTO\ToeflGenerationPlan;
use App\Modules\QuestionEngine\DTO\ToeflGenerationSlot;
use App\Modules\QuestionEngine\DTO\ToeicGenerationPlan;
use App\Modules\QuestionEngine\DTO\ToeicGenerationSlot;
use App\Modules\QuestionEngine\Enums\GenerationBatchStatus;
use App\Modules\QuestionEngine\Enums\GenerationItemStatus;
use App\Modules\QuestionEngine\Models\AssessmentStandard;
use App\Modules\QuestionEngine\Models\QuestionGenerationBatch;
use App\Modules\QuestionEngine\Models\QuestionGenerationItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class GenerationBatchFactory
{
    /**
     * Create persistent generation batch and items from a generation plan.
     *
     * @param  array<string, mixed>  $options  ['question_bank_id' => string, 'requested_by' => int, 'idempotency_key' => string, 'force_new_run' => bool, 'new_run_nonce' => string, 'generation_config' => array]
     *
     * @throws InvalidArgumentException
     */
    public function createFromPlan(ToeicGenerationPlan|ToeflGenerationPlan $plan, array $options = []): QuestionGenerationBatch
    {
        // Issue 2: QuestionBank destination is strictly required
        $questionBankId = $options['question_bank_id'] ?? null;
        if (empty($questionBankId)) {
            throw new InvalidArgumentException('question_bank_id is required to create a generation batch.');
        }

        /** @var QuestionBank|null $questionBank */
        $questionBank = QuestionBank::find($questionBankId);
        if ($questionBank === null || $questionBank->trashed()) {
            throw new InvalidArgumentException("QuestionBank [{$questionBankId}] does not exist or has been deleted.");
        }

        // Issue 8: Validate AssessmentStandard exists and is valid
        $standardId = $plan->assessmentStandardId;
        if (empty($standardId) || !AssessmentStandard::where('id', $standardId)->exists()) {
            throw new InvalidArgumentException("AssessmentStandard [{$standardId}] bound to plan does not exist in database.");
        }

        // Issue 1: Deterministic Idempotency Key
        $promptContractVersion = 'question_generation_v1';
        $keyParts = [
            $questionBankId,
            $plan->fingerprint,
            $promptContractVersion,
        ];

        if (!empty($options['force_new_run'])) {
            $keyParts[] = Str::uuid()->toString();
        } elseif (isset($options['new_run_nonce']) && $options['new_run_nonce'] !== '') {
            $keyParts[] = (string) $options['new_run_nonce'];
        }

        $idempotencyKey = $options['idempotency_key'] ?? ('batch_'.hash('sha256', implode(':', $keyParts)));

        // If an existing batch exists with this idempotency key, return it
        $existing = QuestionGenerationBatch::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($plan, $options, $idempotencyKey, $questionBankId, $standardId, $promptContractVersion) {
            $isToeic = $plan instanceof ToeicGenerationPlan;

            $family = $plan->assessmentFamily;
            $standardVersion = $plan->standardVersion;
            $fingerprint = $plan->fingerprint;
            $plannerType = $isToeic ? 'toeic_blueprint_planner' : 'toefl_blueprint_planner';
            $plannerStrategyVersion = $isToeic ? '2026.1' : ($plan->plannerStrategyVersion ?? '2026.1');
            $totalSlots = $plan->totalSlots;

            $batch = QuestionGenerationBatch::create([
                'question_bank_id' => $questionBankId,
                'assessment_family' => $family,
                'assessment_standard_id' => $standardId,
                'standard_version' => $standardVersion,
                'planner_type' => $plannerType,
                'planner_strategy_version' => $plannerStrategyVersion,
                'plan_fingerprint' => $fingerprint,
                'status' => GenerationBatchStatus::Draft,
                'requested_by' => $options['requested_by'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'prompt_contract_version' => $promptContractVersion,
                'total_slots' => $totalSlots,
                'pending_slots' => $totalSlots,
                'processing_slots' => 0,
                'generated_slots' => 0,
                'validated_slots' => 0,
                'failed_slots' => 0,
                'generation_config' => $options['generation_config'] ?? [],
                'plan_snapshot' => $plan->toArray(),
                'metadata' => $options['metadata'] ?? [],
            ]);

            $slots = $plan->slots;
            foreach ($slots as $slot) {
                if ($slot instanceof ToeicGenerationSlot) {
                    $this->createToeicItem($batch, $slot);
                } elseif ($slot instanceof ToeflGenerationSlot) {
                    $this->createToeflItem($batch, $slot);
                }
            }

            return $batch->fresh(['items']);
        });
    }

    protected function createToeicItem(QuestionGenerationBatch $batch, ToeicGenerationSlot $slot): QuestionGenerationItem
    {
        $slotFingerprint = hash('sha256', json_encode([
            'batch_id' => $batch->id,
            'sequence' => $slot->sequence,
            'part' => $slot->partNumber,
            'proficiency' => $slot->proficiencyTarget->value,
            'difficulty' => $slot->difficulty->value,
            'construct' => $slot->construct->value,
            'domain' => $slot->domain->value,
            'seed' => $slot->seed,
        ]));

        return QuestionGenerationItem::create([
            'generation_batch_id' => $batch->id,
            'slot_sequence' => $slot->sequence,
            'slot_fingerprint' => $slotFingerprint,
            'assessment_family' => $slot->assessmentFamily,
            'assessment_standard_id' => $slot->assessmentStandardId,
            'standard_version' => $slot->standardVersion,
            'section' => $slot->section->value,
            'part_number' => $slot->partNumber,
            'task_type' => "toeic_part_{$slot->partNumber}",
            'claim' => 'Claim — TOEIC '.ucfirst($slot->section->value),
            'skill' => $slot->construct->value,
            'construct' => $slot->construct->value,
            'proficiency_target' => $slot->proficiencyTarget->value,
            'difficulty' => $slot->difficulty->value,
            'domain' => $slot->domain->value,
            'context' => $slot->context?->value,
            'status' => GenerationItemStatus::Pending,
            'attempt_count' => 0,
        ]);
    }

    protected function createToeflItem(QuestionGenerationBatch $batch, ToeflGenerationSlot $slot): QuestionGenerationItem
    {
        $slotFingerprint = hash('sha256', json_encode([
            'batch_id' => $batch->id,
            'sequence' => $slot->sequence,
            'task_type' => $slot->taskType->value,
            'claim' => $slot->claim->value,
            'skill' => $slot->skill->value,
            'proficiency' => $slot->proficiencyTarget?->value,
            'difficulty' => $slot->difficulty?->value,
            'seed' => $slot->seed,
        ]));

        return QuestionGenerationItem::create([
            'generation_batch_id' => $batch->id,
            'slot_sequence' => $slot->sequence,
            'slot_fingerprint' => $slotFingerprint,
            'assessment_family' => $slot->assessmentFamily,
            'assessment_standard_id' => $slot->assessmentStandardId,
            'standard_version' => $slot->standardVersion,
            'section' => $slot->section,
            'part_number' => null,
            'task_type' => $slot->taskType->value,
            'claim' => $slot->claim->value,
            'skill' => $slot->skill->value,
            'construct' => $slot->skill->value,
            'proficiency_target' => $slot->proficiencyTarget?->value,
            'difficulty' => $slot->difficulty?->value,
            'domain' => 'academic',
            'context' => $slot->languageUseContext->value,
            'status' => GenerationItemStatus::Pending,
            'attempt_count' => 0,
        ]);
    }
}
