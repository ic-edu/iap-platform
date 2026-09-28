<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionEngine\Contracts\QuestionPromptComposer;
use App\Modules\QuestionEngine\DTO\PromptComposition;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Models\QuestionGenerationItem;
use InvalidArgumentException;

class ToeflPromptComposer implements QuestionPromptComposer
{
    public const PROMPT_CONTRACT_VERSION = 'question_generation_v1';

    public function supports(AssessmentFamily $family): bool
    {
        return $family === AssessmentFamily::ToeflIbt;
    }

    public function compose(QuestionGenerationItem $item, array $config = []): PromptComposition
    {
        if ($item->assessment_family !== AssessmentFamily::ToeflIbt) {
            throw new InvalidArgumentException("ToeflPromptComposer only supports TOEFL iBT assessment family, got [{$item->assessment_family->value}].");
        }

        $taskType = $item->task_type ?? 'read_in_daily_life';
        $section = $item->section ?? $this->detectSection($taskType);
        $claim = $item->claim ?? 'Claim 1 — Reading';
        $skill = $item->skill ?? 'Reading for basic comprehension';
        $proficiency = $item->proficiency_target ?? 'b2';
        $difficulty = $item->difficulty ?? 'medium';
        $domain = $item->domain ?? 'academic';
        $context = $item->context ?? 'academic';

        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($item, $taskType, $section, $claim, $skill, $proficiency, $difficulty, $domain, $context);
        $structuralConstraints = $this->buildStructuralConstraints($taskType);
        $targetMetadata = [
            'assessment_family' => AssessmentFamily::ToeflIbt->value,
            'assessment_standard_id' => $item->assessment_standard_id,
            'standard_version' => $item->standard_version,
            'section' => $section,
            'task_type' => $taskType,
            'claim' => $claim,
            'skill' => $skill,
            'proficiency_target' => $proficiency,
            'difficulty' => $difficulty,
            'domain' => $domain,
            'context' => $context,
            'slot_sequence' => $item->slot_sequence,
        ];
        $schemaDefinition = $this->buildSchemaDefinition($taskType);

        return new PromptComposition(
            promptContractVersion: self::PROMPT_CONTRACT_VERSION,
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            structuralConstraints: $structuralConstraints,
            targetMetadata: $targetMetadata,
            schemaDefinition: $schemaDefinition,
            examples: [],
            metadata: ['composer' => self::class]
        );
    }

    protected function detectSection(string $taskType): string
    {
        return match ($taskType) {
            'complete_the_words', 'read_in_daily_life', 'read_an_academic_passage' => 'reading',
            'listen_and_choose_a_response', 'listen_to_a_conversation', 'listen_to_an_announcement', 'listen_to_an_academic_talk' => 'listening',
            'build_a_sentence', 'write_an_email', 'write_for_an_academic_discussion' => 'writing',
            'listen_and_repeat', 'take_an_interview' => 'speaking',
            default => 'reading',
        };
    }

    protected function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert psychometric item writer for the TOEFL iBT 2026 examination according to official ETS specifications.
Generate authentic, reliable, and construct-valid assessment items mapped to ETS claims, CEFR levels, and target skills.
Output your response STRICTLY as valid JSON adhering to the specified schema without conversational prefix or suffix.
PROMPT;
    }

    protected function buildUserPrompt(
        QuestionGenerationItem $item,
        string $taskType,
        string $section,
        string $claim,
        string $skill,
        string $proficiency,
        string $difficulty,
        string $domain,
        string $context
    ): string {
        return <<<USER_PROMPT
Create a TOEFL iBT 2026 assessment item for Task [{$taskType}] in the [{$section}] section.

Target Specifications:
- Claim: {$claim}
- Skill: {$skill}
- Proficiency Target: {$proficiency}
- Difficulty: {$difficulty}
- Domain: {$domain}
- Language Context: {$context}

Task Requirements:
- Structure the item according to canonical ETS 2026 guidelines for {$taskType}.
- If multiple-choice, provide exactly 4 options (A-D) with 1 clear correct answer and plausible distractors.
- If open-ended / constructed response (Speaking / Writing), provide the task prompt, evaluation rubric, and an exemplar sample response.
USER_PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuralConstraints(string $taskType): array
    {
        $isConstructed = in_array($taskType, [
            'build_a_sentence',
            'write_an_email',
            'write_for_an_academic_discussion',
            'listen_and_repeat',
            'take_an_interview',
        ], true);

        return [
            'task_type' => $taskType,
            'is_constructed_response' => $isConstructed,
            'choice_count' => $isConstructed ? 0 : 4,
            'requires_rubric' => $isConstructed,
            'requires_sample_response' => $isConstructed,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSchemaDefinition(string $taskType): array
    {
        return [
            'type' => 'object',
            'required' => ['schema_version', 'prompt'],
            'properties' => [
                'schema_version' => ['type' => 'string', 'enum' => ['generated_question_candidate_v1']],
                'prompt' => ['type' => 'string'],
                'passage_text' => ['type' => ['string', 'null']],
                'audio_script' => ['type' => ['string', 'null']],
                'choices' => ['type' => 'array'],
                'correct_answer' => ['type' => ['string', 'null']],
                'explanation' => ['type' => ['string', 'null']],
                'rubric' => ['type' => ['string', 'null']],
                'sample_response' => ['type' => ['string', 'null']],
                'metadata' => ['type' => 'object'],
            ],
        ];
    }
}
