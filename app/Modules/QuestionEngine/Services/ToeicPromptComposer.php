<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionEngine\Contracts\QuestionPromptComposer;
use App\Modules\QuestionEngine\DTO\PromptComposition;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Models\QuestionGenerationItem;
use InvalidArgumentException;

class ToeicPromptComposer implements QuestionPromptComposer
{
    public const PROMPT_CONTRACT_VERSION = 'question_generation_v1';

    public function supports(AssessmentFamily $family): bool
    {
        return $family === AssessmentFamily::Toeic;
    }

    public function compose(QuestionGenerationItem $item, array $config = []): PromptComposition
    {
        if ($item->assessment_family !== AssessmentFamily::Toeic) {
            throw new InvalidArgumentException("ToeicPromptComposer only supports TOEIC assessment family, got [{$item->assessment_family->value}].");
        }

        $part = (int) ($item->part_number ?? 5);
        $section = $item->section ?? ($part <= 4 ? 'listening' : 'reading');
        $proficiency = $item->proficiency_target ?? 'b2';
        $difficulty = $item->difficulty ?? 'medium';
        $construct = $item->construct ?? 'main_idea';
        $domain = $item->domain ?? 'general_business';
        $context = $item->context ?? 'workplace';

        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($item, $part, $section, $proficiency, $difficulty, $construct, $domain, $context);
        $structuralConstraints = $this->buildStructuralConstraints($part);
        $targetMetadata = [
            'assessment_family' => AssessmentFamily::Toeic->value,
            'assessment_standard_id' => $item->assessment_standard_id,
            'standard_version' => $item->standard_version,
            'section' => $section,
            'part_number' => $part,
            'proficiency_target' => $proficiency,
            'difficulty' => $difficulty,
            'construct' => $construct,
            'domain' => $domain,
            'context' => $context,
            'slot_sequence' => $item->slot_sequence,
        ];
        $schemaDefinition = $this->buildSchemaDefinition($part);

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

    protected function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a professional TOEIC-style assessment item writer.
Generate authentic, high-quality, standardized questions strictly adhering to the IAP canonical assessment standard and supplied generation metadata, CEFR proficiency frameworks, and domain taxonomy constraints.
Output your response STRICTLY as valid JSON conforming to the requested schema. Do not enclose your output in extraneous conversational prose.
PROMPT;
    }

    protected function buildUserPrompt(
        QuestionGenerationItem $item,
        int $part,
        string $section,
        string $proficiency,
        string $difficulty,
        string $construct,
        string $domain,
        string $context
    ): string {
        $partGuidelines = match ($part) {
            1 => 'Generate a TOEIC-style Part 1 Photograph question. Provide 4 answer options (A, B, C, D) describing a workplace or public scene. Exactly 1 must be unequivocally correct.',
            2 => 'Generate a TOEIC-style Part 2 Question-Response item. The prompt is a spoken question or statement. Provide exactly 3 options (A, B, C). Exactly 1 option must be logically correct.',
            3 => "Generate a TOEIC-style Part 3 Short Conversation item. Include a realistic business dialogue transcript in 'audio_script', followed by a targeted comprehension question with 4 options (A, B, C, D).",
            4 => "Generate a TOEIC-style Part 4 Short Talk item. Include a realistic workplace announcement, voice message, or report transcript in 'audio_script', followed by a targeted question with 4 options (A, B, C, D).",
            5 => "Generate a TOEIC Part 5 Incomplete Sentence item.
Requirements:
- Exactly one incomplete sentence in 'prompt' containing exactly one blank represented as '_____'.
- Exactly four answer choices (A, B, C, D) with exactly one correct answer and three grammatically plausible distractors.
- Test vocabulary, syntax, or grammar in a realistic professional/workplace context.
- No stimulus reading passage ('passage_text' must be null).
- No audio script ('audio_script' must be null).
- Output must strictly conform to schema_version 'generated_question_candidate_v1'.",
            6 => "Generate a TOEIC-style Part 6 Text Completion item. Provide a short professional document (letter, notice, email) in 'passage_text' containing a contextual blank, with 4 options (A, B, C, D).",
            7 => "Generate a TOEIC-style Part 7 Reading Comprehension item. Provide a realistic business text (email, schedule, article) in 'passage_text', and a question testing information retrieval or inference with 4 options (A, B, C, D).",
            default => "Generate a TOEIC-style Part {$part} question adhering to canonical assessment standards.",
        };

        return <<<USER_PROMPT
Create a TOEIC question for Part {$part} ({$section}).

Target Parameters:
- Target Proficiency: {$proficiency}
- Difficulty: {$difficulty}
- Target Construct: {$construct}
- Business Domain: {$domain}
- Situational Context: {$context}

Guidelines:
{$partGuidelines}

Ensure distractors are plausible, grammatically consistent, and unequivocally incorrect based on the stimulus.
Provide detailed explanations for the correct key and why distractors fail.
USER_PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuralConstraints(int $part): array
    {
        return [
            'part_number' => $part,
            'choice_count' => $part === 2 ? 3 : 4,
            'choice_labels' => $part === 2 ? ['A', 'B', 'C'] : ['A', 'B', 'C', 'D'],
            'requires_passage' => in_array($part, [6, 7], true),
            'requires_audio_script' => in_array($part, [1, 2, 3, 4], true),
            'allow_multiple_correct' => false,
            'blank_count' => $part === 5 ? 1 : ($part === 6 ? 1 : 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSchemaDefinition(int $part): array
    {
        $choiceCount = $part === 2 ? 3 : 4;

        return [
            'type' => 'object',
            'required' => ['schema_version', 'prompt', 'choices', 'correct_answer', 'explanation'],
            'properties' => [
                'schema_version' => ['type' => 'string', 'enum' => ['generated_question_candidate_v1']],
                'prompt' => ['type' => 'string'],
                'passage_text' => ['type' => ['string', 'null']],
                'audio_script' => ['type' => ['string', 'null']],
                'choices' => [
                    'type' => 'array',
                    'minItems' => $choiceCount,
                    'maxItems' => $choiceCount,
                    'items' => [
                        'type' => 'object',
                        'required' => ['label', 'content', 'is_correct'],
                        'properties' => [
                            'label' => ['type' => 'string'],
                            'content' => ['type' => 'string'],
                            'is_correct' => ['type' => 'boolean'],
                            'explanation' => ['type' => ['string', 'null']],
                        ],
                    ],
                ],
                'correct_answer' => ['type' => 'string'],
                'explanation' => ['type' => 'string'],
                'metadata' => ['type' => 'object'],
            ],
        ];
    }
}
