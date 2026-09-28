<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Modules\QuestionEngine\DTO\GeneratedQuestionCandidate;
use App\Modules\QuestionEngine\Enums\ConstructTaxonomy;
use App\Modules\QuestionEngine\Enums\ContentMode;
use App\Modules\QuestionEngine\Enums\ContentOrigin;
use App\Modules\QuestionEngine\Enums\ContextTaxonomy;
use App\Modules\QuestionEngine\Enums\DomainTaxonomy;
use App\Modules\QuestionEngine\Enums\ProficiencyTarget;
use App\Modules\QuestionEngine\Models\QuestionGenerationItem;
use Illuminate\Support\Facades\DB;

class GeneratedQuestionMaterializer
{
    /**
     * Materialize a validated candidate into a persistent Question draft record.
     */
    public function materialize(QuestionGenerationItem $item, GeneratedQuestionCandidate $candidate, array $providerMetadata = []): Question
    {
        return DB::transaction(function () use ($item, $candidate, $providerMetadata) {
            $batch = $item->batch;
            $bankId = $batch?->question_bank_id;

            // Determine SectionType
            $section = $item->section ? SectionType::tryFrom(strtolower($item->section)) : SectionType::Listening;
            if (!$section) {
                $section = in_array(strtolower((string) $item->section), ['reading', 'read']) ? SectionType::Reading : SectionType::Listening;
            }

            // Determine QuestionType
            $questionType = $candidate->isMultipleChoice() ? QuestionType::MultipleChoice : QuestionType::MultipleChoice;

            // Determine DifficultyLevel
            $difficulty = $item->difficulty ? DifficultyLevel::tryFrom(strtolower($item->difficulty)) : DifficultyLevel::Medium;
            if (!$difficulty) {
                $difficulty = DifficultyLevel::Medium;
            }

            // Determine Taxonomies
            $proficiencyTarget = $item->proficiency_target ? ProficiencyTarget::tryFrom(strtolower($item->proficiency_target)) : null;
            $domain = $item->domain ? DomainTaxonomy::tryFrom(strtolower($item->domain)) : null;
            $construct = $item->construct ? ConstructTaxonomy::tryFrom(strtolower($item->construct)) : null;
            $context = $item->context ? ContextTaxonomy::tryFrom(strtolower($item->context)) : null;
            $contentMode = $domain ? ContentMode::DomainSpecific : ContentMode::General;

            $generationMetadata = [
                'batch_id' => $item->generation_batch_id,
                'slot_sequence' => $item->slot_sequence,
                'slot_fingerprint' => $item->slot_fingerprint,
                'prompt_contract_version' => $batch?->prompt_contract_version ?? 'question_generation_v1',
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

            // Idempotency: Check if question was already materialized for this item
            /** @var Question|null $question */
            $question = null;
            if (!empty($item->question_id)) {
                $question = Question::find($item->question_id);
            }

            if (!$question) {
                $question = new Question;
            }

            $question->fill([
                'question_bank_id' => $bankId,
                'prompt' => $candidate->prompt,
                'passage_text' => $candidate->passageText,
                'section' => $section,
                'part_number' => $item->part_number,
                'task_type' => $item->task_type,
                'claim' => $item->claim,
                'skill' => $item->skill,
                'question_type' => $questionType,
                'difficulty' => $difficulty,
                'assessment_family' => $item->assessment_family,
                'assessment_standard_id' => $item->assessment_standard_id,
                'standard_version' => $item->standard_version,
                'proficiency_target' => $proficiencyTarget,
                'content_mode' => $contentMode,
                'domain' => $domain,
                'construct' => $construct,
                'context' => $context,
                'content_origin' => ContentOrigin::Generated,
                'generation_batch_id' => $item->generation_batch_id,
                'generation_metadata' => $generationMetadata,
                'points' => 1,
                'explanation' => $candidate->explanation,
            ]);

            $question->save();

            // Materialize Choices
            if ($candidate->isMultipleChoice()) {
                // Delete existing choices if updating
                $question->choices()->delete();

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

            // Update generation item state
            $item->markMaterialized($question->id);
            $item->save();

            return $question;
        });
    }
}
