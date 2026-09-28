<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Modules\QuestionEngine\DTO\GeneratedQuestionCandidate;
use App\Modules\QuestionEngine\Enums\ConstructTaxonomy;
use App\Modules\QuestionEngine\Enums\ContentMode;
use App\Modules\QuestionEngine\Enums\ContentOrigin;
use App\Modules\QuestionEngine\Enums\ContextTaxonomy;
use App\Modules\QuestionEngine\Enums\DomainTaxonomy;
use App\Modules\QuestionEngine\Enums\GenerationItemStatus;
use App\Modules\QuestionEngine\Enums\ProficiencyTarget;
use App\Modules\QuestionEngine\Models\AssessmentStandard;
use App\Modules\QuestionEngine\Models\QuestionGenerationItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GeneratedQuestionMaterializer
{
    public function __construct(
        protected ?GeneratedQuestionTypeResolver $typeResolver = null
    ) {
        $this->typeResolver ??= new GeneratedQuestionTypeResolver;
    }

    /**
     * Materialize a validated candidate into a persistent Question draft record.
     *
     * @throws InvalidArgumentException
     */
    public function materialize(QuestionGenerationItem $item, GeneratedQuestionCandidate $candidate, array $providerMetadata = []): Question
    {
        return DB::transaction(function () use ($item, $candidate, $providerMetadata) {
            /** @var QuestionGenerationItem|null $lockedItem */
            $lockedItem = QuestionGenerationItem::where('id', $item->id)->lockForUpdate()->first();

            if (!$lockedItem) {
                throw new InvalidArgumentException("Generation item [{$item->id}] not found.");
            }

            // Issue 4 & 10: Idempotency check for already materialized item
            if ($lockedItem->status === GenerationItemStatus::Materialized) {
                if (!empty($lockedItem->question_id)) {
                    $existingQuestion = Question::find($lockedItem->question_id);
                    if ($existingQuestion) {
                        return $existingQuestion;
                    }
                }
            }

            // Issue 4: Materializer must independently enforce: item.status === validated
            if ($lockedItem->status !== GenerationItemStatus::Validated) {
                throw new InvalidArgumentException("Generation item must be in validated status to materialize, got [{$lockedItem->status->value}].");
            }

            $batch = $lockedItem->batch;
            if (!$batch) {
                throw new InvalidArgumentException("Generation item [{$lockedItem->id}] is missing parent batch.");
            }

            // Issue 2 & 9: Batch must have a valid non-deleted QuestionBank
            $bankId = $batch->question_bank_id;
            if (empty($bankId)) {
                throw new InvalidArgumentException("Parent batch [{$batch->id}] has no target QuestionBank.");
            }

            $bank = QuestionBank::find($bankId);
            if (!$bank || $bank->trashed()) {
                throw new InvalidArgumentException("Target QuestionBank [{$bankId}] does not exist or is deleted.");
            }

            // Issue 9: Governance and standard bindings validation
            if ($lockedItem->assessment_family !== $batch->assessment_family) {
                throw new InvalidArgumentException("Item assessment family [{$lockedItem->assessment_family->value}] does not match batch family [{$batch->assessment_family->value}].");
            }

            if ($lockedItem->assessment_standard_id !== $batch->assessment_standard_id) {
                throw new InvalidArgumentException("Item standard ID [{$lockedItem->assessment_standard_id}] does not match batch standard ID [{$batch->assessment_standard_id}].");
            }

            if (empty($lockedItem->assessment_standard_id) || !AssessmentStandard::where('id', $lockedItem->assessment_standard_id)->exists()) {
                throw new InvalidArgumentException("Item standard ID [{$lockedItem->assessment_standard_id}] is invalid or deleted.");
            }

            // Determine SectionType
            $section = $lockedItem->section ? SectionType::tryFrom(strtolower($lockedItem->section)) : SectionType::Listening;
            if (!$section) {
                $section = in_array(strtolower((string) $lockedItem->section), ['reading', 'read']) ? SectionType::Reading : SectionType::Listening;
            }

            // Issue 5: Resolve QuestionType via GeneratedQuestionTypeResolver (NO generic fallback)
            $questionType = $this->typeResolver->resolve($lockedItem, $candidate);

            // Determine DifficultyLevel
            $difficulty = $lockedItem->difficulty ? DifficultyLevel::tryFrom(strtolower($lockedItem->difficulty)) : DifficultyLevel::Medium;
            if (!$difficulty) {
                $difficulty = DifficultyLevel::Medium;
            }

            // Determine Taxonomies
            $proficiencyTarget = $lockedItem->proficiency_target ? ProficiencyTarget::tryFrom(strtolower($lockedItem->proficiency_target)) : null;
            $domain = $lockedItem->domain ? DomainTaxonomy::tryFrom(strtolower($lockedItem->domain)) : null;
            $construct = $lockedItem->construct ? ConstructTaxonomy::tryFrom(strtolower($lockedItem->construct)) : null;
            $context = $lockedItem->context ? ContextTaxonomy::tryFrom(strtolower($lockedItem->context)) : null;
            $contentMode = $domain ? ContentMode::DomainSpecific : ContentMode::General;

            $generationMetadata = [
                'batch_id' => $lockedItem->generation_batch_id,
                'slot_sequence' => $lockedItem->slot_sequence,
                'slot_fingerprint' => $lockedItem->slot_fingerprint,
                'prompt_contract_version' => $batch->prompt_contract_version ?? 'question_generation_v1',
                'schema_version' => $candidate->schemaVersion,
                'provider_name' => $providerMetadata['provider_name'] ?? 'orchestrator',
                'latency_ms' => $providerMetadata['latency_ms'] ?? 0,
                'quality_gate_passed' => true,
                'audio_script' => $candidate->audioScript,
                'rubric' => $candidate->rubric,
                'sample_response' => $candidate->sampleResponse,
                'candidate_metadata' => $candidate->metadata,
                'materialized_at' => now()->toIso8601String(),
            ];

            // Create new Question draft
            $question = new Question;
            $question->fill([
                'question_bank_id' => $bankId,
                'prompt' => $candidate->prompt,
                'passage_text' => $candidate->passageText,
                'section' => $section,
                'part_number' => $lockedItem->part_number,
                'task_type' => $lockedItem->task_type,
                'claim' => $lockedItem->claim,
                'skill' => $lockedItem->skill,
                'question_type' => $questionType,
                'difficulty' => $difficulty,
                'assessment_family' => $lockedItem->assessment_family,
                'assessment_standard_id' => $lockedItem->assessment_standard_id,
                'standard_version' => $lockedItem->standard_version,
                'proficiency_target' => $proficiencyTarget,
                'content_mode' => $contentMode,
                'domain' => $domain,
                'construct' => $construct,
                'context' => $context,
                'content_origin' => ContentOrigin::Generated,
                'generation_batch_id' => $lockedItem->generation_batch_id,
                'generation_metadata' => $generationMetadata,
                'points' => 1,
                'explanation' => $candidate->explanation,
            ]);

            $question->save();

            // Materialize Choices
            if ($candidate->isMultipleChoice()) {
                foreach ($candidate->choices as $idx => $choiceData) {
                    $label = $choiceData['label'] ?? chr(65 + $idx);
                    $content = $choiceData['content'] ?? '';
                    $isCorrect = !empty($choiceData['is_correct']);

                    QuestionChoice::create([
                        'question_id' => $question->id,
                        'label' => $label,
                        'content' => $content,
                        'is_correct' => $isCorrect,
                    ]);
                }
            }

            // Update generation item state to Materialized
            $lockedItem->markMaterialized($question->id);
            $lockedItem->save();

            return $question;
        });
    }
}
