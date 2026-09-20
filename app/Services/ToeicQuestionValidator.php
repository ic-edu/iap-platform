<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Modules\Assessment\Models\Test;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Passage;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ToeicQuestionValidator
{
    /**
     * Canonical TOEIC Part Blueprints: question count targets and number ranges.
     */
    public const TOEIC_PART_BLUEPRINTS = [
        1 => ['target_count' => 6,  'start_number' => 1,   'end_number' => 6,   'name' => 'Photographs',             'section' => 'listening'],
        2 => ['target_count' => 25, 'start_number' => 7,   'end_number' => 31,  'name' => 'Question-Response',       'section' => 'listening'],
        3 => ['target_count' => 39, 'start_number' => 32,  'end_number' => 70,  'name' => 'Conversations',           'section' => 'listening'],
        4 => ['target_count' => 30, 'start_number' => 71,  'end_number' => 100, 'name' => 'Talks',                   'section' => 'listening'],
        5 => ['target_count' => 30, 'start_number' => 101, 'end_number' => 130, 'name' => 'Incomplete Sentences',   'section' => 'reading'],
        6 => ['target_count' => 16, 'start_number' => 131, 'end_number' => 146, 'name' => 'Text Completion',        'section' => 'reading'],
        7 => ['target_count' => 54, 'start_number' => 147, 'end_number' => 200, 'name' => 'Reading Comprehension',   'section' => 'reading'],
    ];

    /**
     * Canonical Full TOEIC Part 7 Blueprint (Reading Comprehension).
     */
    public const TOEIC_PART7_BLUEPRINT = [
        'total_questions' => 54,
        'start_number'    => 147,
        'end_number'      => 200,
        'total_groups'    => 15,

        'single' => [
            'group_count'             => 10,
            'question_total'          => 29,
            'questions_per_group_min' => 2,
            'questions_per_group_max' => 4,
            'document_count'          => 1,
            'start_number'            => 147,
            'end_number'              => 175,
        ],

        'double' => [
            'group_count'         => 2,
            'question_total'      => 10,
            'questions_per_group' => 5,
            'document_count'      => 2,
            'start_number'        => 176,
            'end_number'          => 185,
        ],

        'triple' => [
            'group_count'         => 3,
            'question_total'      => 15,
            'questions_per_group' => 5,
            'document_count'      => 3,
            'start_number'        => 186,
            'end_number'          => 200,
        ],
    ];

    /**
     * Get Part 7 canonical blueprint.
     */
    public static function getPart7Blueprint(): array
    {
        return self::TOEIC_PART7_BLUEPRINT;
    }

    /**
     * Get canonical question range for a Part 7 passage type.
     *
     * @param string $passageType 'single'|'double'|'triple'
     * @return array{start: int, end: int, count: int}|null
     */
    public static function getPart7PassageTypeQuestionRange(string $passageType): ?array
    {
        $type = strtolower($passageType);
        $bp = self::TOEIC_PART7_BLUEPRINT[$type] ?? null;
        if (!$bp) {
            return null;
        }
        return [
            'start' => $bp['start_number'],
            'end'   => $bp['end_number'],
            'count' => $bp['question_total'],
        ];
    }

    /**
     * Get blueprint details for a TOEIC part.
     *
     * @param int|string|null $partNumber
     * @return array{target_count: int, start_number: int, end_number: int, name: string, section: string}|null
     */
    public static function getPartBlueprint(int|string|null $partNumber): ?array
    {
        $part = (int) $partNumber;
        return self::TOEIC_PART_BLUEPRINTS[$part] ?? null;
    }

    /**
     * Get canonical target question count for a TOEIC part.
     *
     * @param int|string|null $partNumber
     * @return int|null
     */
    public static function getPartTargetQuestionCount(int|string|null $partNumber): ?int
    {
        $blueprint = self::getPartBlueprint($partNumber);
        return $blueprint['target_count'] ?? null;
    }

    /**
     * Get canonical question range [start, end] for a TOEIC part.
     *
     * @param int|string|null $partNumber
     * @return array{start: int, end: int}|null
     */
    public static function getPartQuestionRange(int|string|null $partNumber): ?array
    {
        $blueprint = self::getPartBlueprint($partNumber);
        if (!$blueprint) {
            return null;
        }
        return [
            'start' => $blueprint['start_number'],
            'end'   => $blueprint['end_number'],
        ];
    }

    /**
     * Total canonical questions in a full TOEIC test (200).
     */
    public static function getTotalCanonicalTargetCount(): int
    {
        return 200;
    }

    /**
     * Get all TOEIC part blueprints.
     *
     * @return array<int, array{target_count: int, start_number: int, end_number: int, name: string, section: string}>
     */
    public static function getAllPartBlueprints(): array
    {
        return self::TOEIC_PART_BLUEPRINTS;
    }

    /**
     * Detect canonical TOEIC part number (1..7) from section or question context.
     *
     * @param mixed $section
     * @param mixed $question
     * @return int|null
     */
    public static function detectPartNumber(mixed $section, mixed $question = null): ?int
    {
        // 1. Check explicit question part_number if provided
        if ($question) {
            $qPart = is_array($question) ? ($question['part_number'] ?? null) : ($question->part_number ?? null);
            if ($qPart && (int) $qPart >= 1 && (int) $qPart <= 7) {
                return (int) $qPart;
            }
        }

        // 2. Check section title regex 'Part X'
        if ($section) {
            $title = is_array($section) ? ($section['title'] ?? '') : ($section->title ?? '');
            if (preg_match('/Part\s*([1-7])/i', (string) $title, $m)) {
                return (int) $m[1];
            }

            // 3. Check first question in section if loaded
            if ($section instanceof \App\Modules\Assessment\Models\TestSection && $section->relationLoaded('testQuestions')) {
                $firstQ = $section->testQuestions->first()?->question;
                if ($firstQ && $firstQ->part_number && (int) $firstQ->part_number >= 1 && (int) $firstQ->part_number <= 7) {
                    return (int) $firstQ->part_number;
                }
            }

            // 4. Check section order if between 1 and 7
            $order = is_array($section) ? ($section['order'] ?? null) : ($section->order ?? null);
            if ($order && (int) $order >= 1 && (int) $order <= 7) {
                return (int) $order;
            }
        }

        return null;
    }

    /**
     * Evaluate the completeness state of a TOEIC part (under, exact, over).
     *
     * @param int|string|null $partNumber
     * @param int $completeCount
     * @param int $totalCount
     * @return array{
     *     target_count: int,
     *     complete_count: int,
     *     total_count: int,
     *     state: 'under'|'exact'|'over',
     *     is_exact: bool,
     *     is_under: bool,
     *     is_over: bool,
     *     diff: int,
     *     status: 'ready'|'needs_attention',
     *     status_message: string
     * }
     */
    public static function evaluatePartCompleteness(int|string|null $partNumber, int $completeCount, int $totalCount = 0): array
    {
        $blueprint = self::getPartBlueprint($partNumber);
        $target = $blueprint['target_count'] ?? $totalCount;
        $total = max($totalCount, $completeCount);

        if ($total > $target || $completeCount > $target) {
            $state = 'over';
            $diff = max($total, $completeCount) - $target;
            $msg = "{$diff} " . \Illuminate\Support\Str::plural('question', $diff) . " exceed TOEIC blueprint";
            $status = 'needs_attention';
        } elseif ($completeCount < $target) {
            $state = 'under';
            $diff = $target - $completeCount;
            $msg = "{$diff} " . \Illuminate\Support\Str::plural('question', $diff) . " missing";
            $status = 'needs_attention';
        } else {
            $state = 'exact';
            $diff = 0;
            $msg = "Ready";
            $status = 'ready';
        }

        return [
            'target_count'   => $target,
            'complete_count' => $completeCount,
            'total_count'    => $total,
            'state'          => $state,
            'is_exact'       => $state === 'exact',
            'is_under'       => $state === 'under',
            'is_over'        => $state === 'over',
            'diff'           => $diff,
            'status'         => $status,
            'status_message' => $msg,
        ];
    }

    /**
     * Compute canonical question display number and overflow status for a slot in a part.
     *
     * @param int|string|null $partNumber
     * @param int $slotIndexInPart 1-indexed position of question within this part
     * @return array{
     *     number: int|null,
     *     display_number: string,
     *     label: string,
     *     is_overflow: bool,
     *     overflow_index: int|null
     * }
     */
    public static function getQuestionDisplayNumber(int|string|null $partNumber, int $slotIndexInPart): array
    {
        $blueprint = self::getPartBlueprint($partNumber);
        if (!$blueprint) {
            return [
                'number'         => $slotIndexInPart,
                'display_number' => (string) $slotIndexInPart,
                'label'          => "Q{$slotIndexInPart}",
                'is_overflow'    => false,
                'overflow_index' => null,
            ];
        }

        $target = $blueprint['target_count'];
        $start = $blueprint['start_number'];

        if ($slotIndexInPart <= $target) {
            $canonicalNum = $start + $slotIndexInPart - 1;
            return [
                'number'         => $canonicalNum,
                'display_number' => (string) $canonicalNum,
                'label'          => "Q{$canonicalNum}",
                'is_overflow'    => false,
                'overflow_index' => null,
            ];
        }

        $overflowIdx = $slotIndexInPart - $target;
        return [
            'number'         => null,
            'display_number' => "Overflow {$overflowIdx}",
            'label'          => "Overflow {$overflowIdx}",
            'is_overflow'    => true,
            'overflow_index' => $overflowIdx,
        ];
    }

    /**
     * Determine if the context or entity is for a TOEIC assessment/question bank.
     */
    public static function isToeic(mixed $context): bool
    {
        if (!$context) {
            return false;
        }

        if ($context instanceof Test) {
            $type = is_object($context->test_type) ? $context->test_type->value : (string) $context->test_type;
            return strtolower($type) === 'toeic';
        }

        if ($context instanceof QuestionBank) {
            $type = is_object($context->test_type) ? $context->test_type->value : (string) $context->test_type;
            return strtolower($type) === 'toeic';
        }

        if ($context instanceof Question) {
            if ($context->questionBank) {
                return self::isToeic($context->questionBank);
            }
            // For Assessment-authored questions, check linked tests
            $test = Test::whereHas('sections.testQuestions', function ($q) use ($context) {
                $q->where('question_id', $context->id);
            })->first();

            if ($test) {
                return self::isToeic($test);
            }

            // If question has part_number 1..7 or explicit exam_type
            return !empty($context->part_number);
        }

        if (is_array($context)) {
            $type = $context['test_type'] ?? null;
            if ($type) {
                $val = is_object($type) ? $type->value : (string) $type;
                return strtolower($val) === 'toeic';
            }
            return !empty($context['part_number']) || !empty($context['is_toeic']);
        }

        if (is_string($context)) {
            return strtolower($context) === 'toeic';
        }

        return false;
    }

    /**
     * Derive canonical section for a given TOEIC part number.
     */
    public static function deriveSection(int $partNumber): string
    {
        return in_array($partNumber, [1, 2, 3, 4], true) ? 'listening' : 'reading';
    }

    /**
     * Determine if a TOEIC part is audio-only for choice content (Part 1 and Part 2).
     */
    public static function isAudioOnlyChoicePart(int|string|null $partNumber): bool
    {
        return in_array((int) $partNumber, [1, 2], true);
    }

    /**
     * Determine whether an item or context requires choice text.
     */
    public static function requiresChoiceText(mixed $context, int|string|null $partNumber = null): bool
    {
        if ($partNumber !== null) {
            return !self::isAudioOnlyChoicePart($partNumber);
        }

        if ($context instanceof Question) {
            return !self::isAudioOnlyChoicePart($context->part_number);
        }

        if (is_array($context) && isset($context['part_number'])) {
            return !self::isAudioOnlyChoicePart($context['part_number']);
        }

        return true;
    }

    /**
     * Determine whether an item or context is an audio-only choice item (Part 1 and Part 2).
     */
    public static function isAudioOnlyChoiceItem(mixed $context, int|string|null $partNumber = null): bool
    {
        return !self::requiresChoiceText($context, $partNumber);
    }

    /**
     * Determine if a TOEIC part requires prompt text (Part 1, 2, and 6 prompts are optional).
     */
    public static function requiresPrompt(int|string|null $partNumber): bool
    {
        return !in_array((int) $partNumber, [1, 2, 6], true);
    }

    /**
     * Check TOEIC question rules without throwing.
     * Returns an array: ['is_valid' => bool, 'errors' => string[], 'section' => string, 'part_number' => int]
     *
     * @param array<string, mixed> $data
     * @param Question|null $question Existing question model (if updating)
     * @return array<string, mixed>
     */
    public static function check(array $data, ?Question $question = null): array
    {
        $errors = [];

        // 1. Part Number Validation
        $partNumber = isset($data['part_number']) && $data['part_number'] !== '' ? (int) $data['part_number'] : ($question?->part_number ?? null);

        if (empty($partNumber) || !in_array($partNumber, [1, 2, 3, 4, 5, 6, 7], true)) {
            $errors['part_number'] = 'TOEIC question requires a valid Part number (1 to 7).';
            return [
                'is_valid'    => false,
                'errors'      => $errors,
                'section'     => 'reading',
                'part_number' => $partNumber,
            ];
        }

        $section = self::deriveSection($partNumber);

        // 2. Prompt Validation
        $prompt = $data['prompt'] ?? ($question?->prompt ?? '');
        if (!in_array($partNumber, [1, 2, 6], true) && empty(trim((string) $prompt))) {
            $errors['prompt'] = "Part {$partNumber} requires a question prompt.";
        }

        // 3. Difficulty Validation (Auto-computed if not provided)
        $hasDiffKey = array_key_exists('difficulty', $data);
        if ($hasDiffKey && empty($data['difficulty'])) {
            $errors['difficulty'] = "Part {$partNumber} requires a difficulty level (Easy, Medium, or Hard).";
        } else {
            $difficulty = $data['difficulty'] ?? ($question?->difficulty ?? null);
            if (empty($difficulty)) {
                $detection = QuestionDifficultyDetectionService::detect($data, $question);
                $difficulty = $detection['difficulty_level'] ?? 'medium';
            }
        }

        // 4. Resolve Choices & Correct Answer
        $choicesInfo = self::resolveChoicesInfo($data, $question, $partNumber);
        $choiceCount = $choicesInfo['count'];
        $hasCorrect = $choicesInfo['has_correct'];

        if (!$hasCorrect && $choiceCount > 0) {
            $errors['correct_choice'] = 'Please select the correct answer.';
        }

        // 5. Resolve Media Assets
        $mediaInfo = self::resolveMediaInfo($data, $question);
        $hasImage   = $mediaInfo['has_image'];
        $hasAudio   = $mediaInfo['has_audio'];
        $hasPassage = $mediaInfo['has_passage'];

        // 6. Part-Specific Rules Enforcement
        switch ($partNumber) {
            case 1:
                // Part 1: Photographs (6 Qs)
                // image REQUIRED, audio REQUIRED, exactly 4 choices
                if (!$hasImage && !$hasAudio) {
                    $errors['media'] = 'Part 1 requires both an image and an audio attachment.';
                } elseif (!$hasImage) {
                    $errors['image_url'] = 'Part 1 requires an image attachment.';
                } elseif (!$hasAudio) {
                    $errors['audio_url'] = 'Part 1 requires an audio attachment.';
                }

                if ($choiceCount !== 4) {
                    $errors['choices'] = 'Part 1 requires exactly 4 answer choices (A, B, C, D).';
                }

                if ($choicesInfo['correct_count'] !== 1) {
                    $errors['correct_choice'] = 'Part 1 requires exactly one correct answer choice.';
                }

                if (!empty($choicesInfo['labels']) && count($choicesInfo['labels']) === 4) {
                    $expectedLabels = ['A', 'B', 'C', 'D'];
                    if (array_values($choicesInfo['labels']) !== $expectedLabels) {
                        $errors['choice_labels'] = 'Part 1 choices must be labeled A, B, C, and D.';
                    }
                }
                break;

            case 2:
                // Part 2: Question-Response (25 Qs)
                // audio REQUIRED, exactly 3 choices (A, B, C), 4th choice FORBIDDEN, exactly 1 correct answer
                if (!$hasAudio) {
                    $errors['audio_url'] = 'Part 2 requires an audio attachment.';
                }

                if ($choiceCount !== 3) {
                    $errors['choices'] = 'Part 2 requires exactly 3 answer choices (A, B, C). Fourth choice (D) is forbidden.';
                }

                if ($choicesInfo['correct_count'] > 1) {
                    $errors['correct_choice'] = 'Part 2 requires exactly one correct answer choice.';
                }

                if (!empty($choicesInfo['labels']) && count($choicesInfo['labels']) === 3) {
                    $expectedLabels = ['A', 'B', 'C'];
                    if (array_values($choicesInfo['labels']) !== $expectedLabels) {
                        $errors['choice_labels'] = 'Part 2 choices must be labeled A, B, and C.';
                    }
                }
                break;

            case 3:
                // Part 3: Conversations (39 Qs)
                // prompt REQUIRED, audio REQUIRED, exactly 4 choices
                if (!$hasAudio) {
                    $errors['audio_url'] = 'Part 3 requires an audio attachment.';
                }

                if ($choiceCount !== 4) {
                    $errors['choices'] = 'Part 3 requires exactly 4 answer choices (A, B, C, D).';
                }
                break;

            case 4:
                // Part 4: Talks (30 Qs)
                // prompt REQUIRED, audio REQUIRED, exactly 4 choices
                if (!$hasAudio) {
                    $errors['audio_url'] = 'Part 4 requires an audio attachment.';
                }

                if ($choiceCount !== 4) {
                    $errors['choices'] = 'Part 4 requires exactly 4 answer choices (A, B, C, D).';
                }
                break;

            case 5:
                // Part 5: Incomplete Sentences (30 Qs)
                // prompt REQUIRED, exactly 4 choices, audio FORBIDDEN
                if ($hasAudio) {
                    $errors['audio_url'] = 'Part 5 does not allow an audio attachment.';
                }

                if ($choiceCount !== 4) {
                    $errors['choices'] = 'Part 5 requires exactly 4 answer choices (A, B, C, D).';
                }
                break;

            case 6:
                // Part 6: Text Completion (16 Qs)
                // passage REQUIRED, prompt REQUIRED, exactly 4 choices
                if (!$hasPassage) {
                    $errors['passage'] = 'Part 6 requires a passage.';
                }

                if ($choiceCount !== 4) {
                    $errors['choices'] = 'Part 6 requires exactly 4 answer choices (A, B, C, D).';
                }
                break;

            case 7:
                // Part 7: Reading Comprehension (54 Qs)
                // passage REQUIRED, prompt REQUIRED, exactly 4 choices
                if (!$hasPassage) {
                    $errors['passage'] = 'Part 7 requires a passage.';
                }

                if ($choiceCount !== 4) {
                    $errors['choices'] = 'Part 7 requires exactly 4 answer choices (A, B, C, D).';
                }
                break;
        }

        return [
            'valid'       => empty($errors),
            'is_valid'    => empty($errors),
            'errors'      => $errors,
            'section'     => $section,
            'part_number' => $partNumber,
        ];
    }

    /**
     * Validate TOEIC question data and throw ValidationException if invalid.
     *
     * @param array<string, mixed> $data
     * @param Question|null $question Existing question model (if updating)
     * @throws ValidationException
     */
    public static function validate(array $data, ?Question $question = null): array
    {
        $result = self::check($data, $question);

        if (!$result['is_valid']) {
            throw ValidationException::withMessages($result['errors']);
        }

        return $result;
    }

    /**
     * Helper to resolve choices count and correct answer status from array or Question model.
     *
     * @param array<string, mixed> $data
     * @param Question|null $question
     * @param int|null $partNumber
     * @return array{count: int, has_correct: bool, correct_count: int, labels: array<string>}
     */
    protected static function resolveChoicesInfo(array $data, ?Question $question = null, ?int $partNumber = null): array
    {
        // 1. From request data array
        if (isset($data['choices']) && is_array($data['choices'])) {
            $rawChoices = $data['choices'];
            $correctChoice = $data['correct_choice'] ?? ($data['correct_choice_id'] ?? null);
            $correctChoices = $data['correct_choices'] ?? [];

            $validCount = 0;
            $hasCorrect = false;
            $correctCount = 0;
            $labels = [];

            foreach ($rawChoices as $idx => $choice) {
                $content = '';
                $isCorrect = false;
                $label = is_array($choice) ? ($choice['label'] ?? chr(65 + $idx)) : chr(65 + $idx);

                if (is_array($choice)) {
                    $content = $choice['content'] ?? ($choice['choice_text'] ?? '');
                    $isCorrect = !empty($choice['is_correct']);
                } elseif (is_string($choice)) {
                    $content = $choice;
                }

                // Check if this choice index or id matches correct selector
                if (!is_null($correctChoice) && $correctChoice !== '' && (string) $idx === (string) $correctChoice) {
                    $isCorrect = true;
                }
                if (in_array((string) $idx, array_map('strval', (array)$correctChoices), true)) {
                    $isCorrect = true;
                }

                if (in_array((int) $partNumber, [1, 2], true)) {
                    $validCount++;
                    $labels[] = strtoupper((string) $label);
                    if ($isCorrect) {
                        $hasCorrect = true;
                        $correctCount++;
                    }
                } else {
                    // Count if content is not empty, or if explicit choices array passed with labels
                    if (!empty(trim((string) $content)) || (is_array($choice) && !empty($choice['label']))) {
                        $validCount++;
                        $labels[] = strtoupper((string) $label);
                        if ($isCorrect) {
                            $hasCorrect = true;
                            $correctCount++;
                        }
                    }
                }
            }

            return [
                'count'         => $validCount,
                'has_correct'   => $hasCorrect,
                'correct_count' => $correctCount,
                'labels'        => $labels,
            ];
        }

        // 2. From existing question model relations
        if ($question) {
            $choices = $question->choices()->get();
            if (in_array((int) $partNumber, [1, 2], true)) {
                $count = $choices->count();
            } else {
                $count = $choices->filter(fn($c) => !empty(trim((string) ($c->content ?? $c->choice_text ?? ''))))->count();
                if ($count === 0 && $choices->count() > 0) {
                    $count = $choices->count();
                }
            }
            $hasCorrect = $choices->contains(fn($c) => (bool) $c->is_correct);
            $correctCount = $choices->filter(fn($c) => (bool) $c->is_correct)->count();
            $labels = $choices->map(fn($c) => strtoupper((string) $c->label))->values()->all();

            return [
                'count'         => $count,
                'has_correct'   => $hasCorrect,
                'correct_count' => $correctCount,
                'labels'        => $labels,
            ];
        }

        return [
            'count'         => 0,
            'has_correct'   => false,
            'correct_count' => 0,
            'labels'        => [],
        ];
    }

    /**
     * Helper to resolve Image, Audio, and Passage presence across URLs, IDs, and models.
     *
     * @param array<string, mixed> $data
     * @param Question|null $question
     * @return array{has_image: bool, has_audio: bool, has_passage: bool}
     */
    protected static function resolveMediaInfo(array $data, ?Question $question = null): array
    {
        $hasImage = !empty($data['image_url']) || !empty($data['image_media_asset_id']);
        $hasAudio = !empty($data['audio_url']) || !empty($data['audio_media_asset_id']);
        $hasPassage = !empty($data['passage_id']) || !empty(trim((string) ($data['passage_text'] ?? '')));

        // Check explicit image_media_asset_id
        if (!empty($data['image_media_asset_id'])) {
            $hasImage = true;
        }

        // Check explicit audio_media_asset_id
        if (!empty($data['audio_media_asset_id'])) {
            $hasAudio = true;
        }

        // Check if question references a shared AudioGroup
        $audioGroupId = $data['audio_group_id'] ?? ($question?->audio_group_id ?? null);
        if (!empty($audioGroupId)) {
            $audioGroup = \App\Modules\QuestionBank\Models\AudioGroup::find($audioGroupId);
            if ($audioGroup && (!empty($audioGroup->audio_url) || !empty($audioGroup->media_asset_id))) {
                $hasAudio = true;
            }
        }

        // Check if question references a shared PassageGroup
        $passageGroupId = $data['passage_group_id'] ?? ($question?->passage_group_id ?? null);
        if (!empty($passageGroupId)) {
            $pGroup = \App\Modules\QuestionBank\Models\PassageGroup::find($passageGroupId);
            if ($pGroup && $pGroup->passages()->exists()) {
                $hasPassage = true;
            }
        }

        $mediaAssetId = $data['media_asset_id'] ?? null;
        if (!empty($mediaAssetId)) {
            $mediaAsset = MediaAsset::find($mediaAssetId);
            if ($mediaAsset) {
                if ($mediaAsset->type === 'image') {
                    $hasImage = true;
                } elseif ($mediaAsset->type === 'audio') {
                    $hasAudio = true;
                } elseif ($mediaAsset->type === 'passage') {
                    $hasPassage = true;
                }
            }
        }

        // Fallback to existing question model
        if ($question) {
            if (!$hasImage && (!empty($question->image_url) || !empty($question->image_media_asset_id))) {
                $hasImage = true;
            }
            if (!$hasAudio && (!empty($question->audio_url) || !empty($question->audio_media_asset_id))) {
                $hasAudio = true;
            }
            if (!$hasAudio && $question->audioGroup && (!empty($question->audioGroup->audio_url) || !empty($question->audioGroup->media_asset_id))) {
                $hasAudio = true;
            }
            if (!$hasPassage && (!empty($question->passage_id) || !empty(trim((string) ($question->passage_text ?? ''))))) {
                $hasPassage = true;
            }
            if (!$hasPassage && $question->passageGroup && $question->passageGroup->passages()->exists()) {
                $hasPassage = true;
            }
            if ($question->imageMediaAsset) {
                $hasImage = true;
            }
            if ($question->audioMediaAsset) {
                $hasAudio = true;
            }
            if ($question->mediaAsset) {
                if ($question->mediaAsset->type === 'image') {
                    $hasImage = true;
                } elseif ($question->mediaAsset->type === 'audio') {
                    $hasAudio = true;
                } elseif ($question->mediaAsset->type === 'passage') {
                    $hasPassage = true;
                }
            }
        }

        return [
            'has_image'   => $hasImage,
            'has_audio'   => $hasAudio,
            'has_passage' => $hasPassage,
        ];
    }

    /**
     * Check TOEIC Audio Group rules (Part 3 Conversations & Part 4 Talks).
     *
     * @param \App\Modules\QuestionBank\Models\AudioGroup|array<string, mixed> $group
     * @param array<int, mixed> $questionsData
     * @return array<string, mixed>
     */
    public static function checkAudioGroup(mixed $group, array $questionsData = []): array
    {
        $errors = [];
        $isModel = $group instanceof \App\Modules\QuestionBank\Models\AudioGroup;

        $groupType = $isModel ? $group->group_type : ($group['group_type'] ?? 'conversation');
        $partNumber = $isModel ? (int) $group->part_number : (int) ($group['part_number'] ?? ($groupType === 'talk' ? 4 : 3));

        if (!in_array($partNumber, [3, 4], true)) {
            $errors['part_number'] = 'Audio Group part number must be 3 (Conversation) or 4 (Talk).';
        }

        if ($partNumber === 3 && $groupType !== 'conversation') {
            $errors['group_type'] = 'Part 3 audio group must have group_type set to conversation.';
        } elseif ($partNumber === 4 && $groupType !== 'talk') {
            $errors['group_type'] = 'Part 4 audio group must have group_type set to talk.';
        }

        // Audio presence at group level
        $hasAudio = false;
        if ($isModel) {
            $hasAudio = !empty($group->audio_url) || !empty($group->media_asset_id);
        } else {
            $hasAudio = !empty($group['audio_url']) || !empty($group['media_asset_id']);
        }

        if (!$hasAudio) {
            $errors['audio_url'] = 'Audio group requires a valid audio attachment.';
        }

        // Question count check (Must be EXACTLY 3 questions)
        $questionCount = 0;
        if (!empty($questionsData)) {
            $questionCount = count($questionsData);
        } elseif ($isModel) {
            $questionCount = $group->questions()->count();
        } elseif (isset($group['questions']) && is_array($group['questions'])) {
            $questionCount = count($group['questions']);
        }

        if ($questionCount !== 3) {
            $errors['question_count'] = "Audio Group for Part {$partNumber} requires exactly 3 questions (found {$questionCount}).";
        }

        // Check each question in the group
        $qList = !empty($questionsData) ? $questionsData : ($isModel ? $group->questions()->with('choices')->get() : ($group['questions'] ?? []));
        $idx = 1;
        foreach ($qList as $qItem) {
            $qData = is_array($qItem) ? $qItem : $qItem->toArray();
            $qData['part_number'] = $partNumber;
            // Group audio satisfies child audio requirement
            if ($hasAudio) {
                $qData['audio_url'] = $qData['audio_url'] ?? ($isModel ? $group->audio_url : ($group['audio_url'] ?? 'group_audio'));
            }
            $qCheck = self::check($qData, is_object($qItem) && $qItem instanceof Question ? $qItem : null);
            if (!$qCheck['is_valid']) {
                foreach ($qCheck['errors'] as $errKey => $errMsg) {
                    if ($errKey !== 'audio_url' && $errKey !== 'media') {
                        $errors["question_{$idx}_{$errKey}"] = "Question #{$idx}: {$errMsg}";
                    }
                }
            }
            $idx++;
        }

        return [
            'is_valid'       => empty($errors),
            'errors'         => $errors,
            'group_type'     => $groupType,
            'part_number'    => $partNumber,
            'question_count' => $questionCount,
        ];
    }

    /**
     * Validate TOEIC Audio Group and throw ValidationException if invalid.
     *
     * @param \App\Modules\QuestionBank\Models\AudioGroup|array<string, mixed> $group
     * @param array<int, mixed> $questionsData
     * @throws ValidationException
     */
    public static function validateAudioGroup(mixed $group, array $questionsData = []): array
    {
        $result = self::checkAudioGroup($group, $questionsData);

        if (!$result['is_valid']) {
            throw ValidationException::withMessages($result['errors']);
        }

        return $result;
    }

    /**
     * Check TOEIC Passage Group rules (Part 6 Text Completion & Part 7 Reading Comprehension).
     *
     * @param \App\Modules\QuestionBank\Models\PassageGroup|array<string, mixed> $group
     * @param array<int, mixed> $passagesData
     * @param array<int, mixed> $questionsData
     * @return array<string, mixed>
     */
    public static function checkPassageGroup(mixed $group, array $passagesData = [], array $questionsData = []): array
    {
        $errors = [];
        $isModel = $group instanceof \App\Modules\QuestionBank\Models\PassageGroup;

        $partNumber = $isModel ? (int) $group->part_number : (int) ($group['part_number'] ?? 7);
        $passageType = $isModel ? (string) $group->passage_type : (string) ($group['passage_type'] ?? 'single');

        // 1. Part number check
        if (!in_array($partNumber, [6, 7], true)) {
            $errors['part_number'] = 'Passage Group part number must be 6 (Text Completion) or 7 (Reading Comprehension).';
        }

        // 2. Section is reading
        $section = self::deriveSection($partNumber);
        if ($section !== 'reading') {
            $errors['section'] = 'Passage Group section must be reading.';
        }

        // 3. Passage type check
        if ($partNumber === 6 && $passageType !== 'single') {
            $errors['passage_type'] = 'Part 6 passage group must be single passage type.';
        } elseif ($partNumber === 7 && !in_array($passageType, ['single', 'double', 'triple'], true)) {
            $errors['passage_type'] = 'Part 7 passage group type must be single, double, or triple.';
        }

        // 4. Resolve and validate Passages
        $pList = !empty($passagesData)
            ? $passagesData
            : ($isModel ? $group->passages()->orderBy('order_in_group', 'asc')->get() : ($group['passages'] ?? []));

        $passageCount = count($pList);
        $expectedPassageCount = match ($partNumber) {
            6 => 1,
            7 => match ($passageType) {
                'double' => 2,
                'triple' => 3,
                default  => 1,
            },
            default => 1,
        };

        if ($passageCount !== $expectedPassageCount) {
            $errors['passage_count'] = match ($partNumber) {
                6 => "Part 6 requires exactly 1 passage (found {$passageCount}).",
                7 => match ($passageType) {
                    'double' => "Part 7 Double Passage requires exactly 2 passages (found {$passageCount}).",
                    'triple' => "Part 7 Triple Passage requires exactly 3 passages (found {$passageCount}).",
                    default  => "Part 7 Single Passage requires exactly 1 passage (found {$passageCount}).",
                },
                default => "Passage Group requires {$expectedPassageCount} passage(s) (found {$passageCount}).",
            };
        }

        // Validate each passage content and ordering
        $validDocTypes = ['email', 'memo', 'notice', 'advertisement', 'article', 'letter', 'chat', 'schedule', 'form', 'invoice', 'webpage', 'message', 'other'];
        $pIdx = 1;
        foreach ($pList as $pItem) {
            $pData = is_array($pItem) ? $pItem : $pItem->toArray();
            $content = $pData['content'] ?? ($pData['passage_text'] ?? '');
            $hasText = !empty(trim((string) $content));
            $hasImage = !empty($pData['image_url']) || !empty($pData['media_asset_id']);
            $contentMode = $pData['content_mode'] ?? null;

            if ($partNumber === 6) {
                // Part 6 requires text
                if (!$hasText) {
                    $errors["passage_{$pIdx}_content"] = "Passage #{$pIdx} text content cannot be empty.";
                }
            } else {
                // Part 7 supports Text, Image / Visual Document, or Text + Image
                if ($contentMode === 'image') {
                    if (!$hasImage) {
                        $errors["passage_{$pIdx}_image"] = "Passage #{$pIdx} requires an attached visual document image.";
                    }
                } elseif ($contentMode === 'text') {
                    if (!$hasText) {
                        $errors["passage_{$pIdx}_content"] = "Passage #{$pIdx} text content cannot be empty.";
                    }
                } elseif ($contentMode === 'text_image') {
                    if (!$hasText && !$hasImage) {
                        $errors["passage_{$pIdx}_content"] = "Passage #{$pIdx} requires text content or an attached visual document.";
                    } elseif (!$hasText) {
                        $errors["passage_{$pIdx}_content"] = "Passage #{$pIdx} text content cannot be empty in Text + Image mode.";
                    } elseif (!$hasImage) {
                        $errors["passage_{$pIdx}_image"] = "Passage #{$pIdx} requires an attached visual document in Text + Image mode.";
                    }
                } else {
                    // Default / fallback: must have at least one usable stimulus (text or image)
                    if (!$hasText && !$hasImage) {
                        $errors["passage_{$pIdx}_content"] = "Passage #{$pIdx} must contain text or an attached visual document.";
                    }
                }
            }

            $order = isset($pData['order_in_group']) ? (int) $pData['order_in_group'] : (isset($pData['order']) ? (int) $pData['order'] : $pIdx);
            if ($order !== $pIdx) {
                $errors["passage_{$pIdx}_order"] = "Passage #{$pIdx} order is invalid (expected {$pIdx}, got {$order}).";
            }

            $docType = strtolower((string) ($pData['document_type'] ?? 'article'));
            if (!empty($docType) && !in_array($docType, $validDocTypes, true)) {
                $errors["passage_{$pIdx}_document_type"] = "Passage #{$pIdx} has invalid document type '{$docType}'.";
            }

            $pIdx++;
        }

        // 5. Resolve and validate Questions
        $qList = !empty($questionsData)
            ? $questionsData
            : ($isModel ? $group->questions()->with('choices')->get() : ($group['questions'] ?? []));

        $questionCount = count($qList);

        if ($partNumber === 6) {
            if ($questionCount !== 4) {
                $errors['question_count'] = "Part 6 Text Completion group requires exactly 4 questions (found {$questionCount}).";
            }
        } elseif ($partNumber === 7) {
            if ($passageType === 'single') {
                if ($questionCount < 2 || $questionCount > 4) {
                    $errors['question_count'] = "Part 7 Single Passage group requires 2 to 4 questions (found {$questionCount}).";
                }
            } elseif ($passageType === 'double') {
                if ($questionCount !== 5) {
                    $errors['question_count'] = "Part 7 Double Passage group requires exactly 5 questions (found {$questionCount}).";
                }
            } elseif ($passageType === 'triple') {
                if ($questionCount !== 5) {
                    $errors['question_count'] = "Part 7 Triple Passage group requires exactly 5 questions (found {$questionCount}).";
                }
            }
        }

        // Validate each question in group
        $qIdx = 1;
        foreach ($qList as $qItem) {
            $qData = is_array($qItem) ? $qItem : $qItem->toArray();
            $qData['part_number'] = $partNumber;
            // Passage group satisfies passage requirement for child questions
            if ($passageCount > 0) {
                $qData['passage_text'] = $qData['passage_text'] ?? 'group_passage';
            }

            // Audio is strictly forbidden for Part 6 and 7
            if (!empty($qData['audio_url']) || !empty($qData['media_asset_id'])) {
                $mediaAsset = !empty($qData['media_asset_id']) ? MediaAsset::find($qData['media_asset_id']) : null;
                if (!empty($qData['audio_url']) || ($mediaAsset && $mediaAsset->type === 'audio')) {
                    $errors["question_{$qIdx}_audio"] = "Question #{$qIdx}: Part {$partNumber} does not allow audio attachments.";
                }
            }

            $qCheck = self::check($qData, is_object($qItem) && $qItem instanceof Question ? $qItem : null);
            if (!$qCheck['is_valid']) {
                foreach ($qCheck['errors'] as $errKey => $errMsg) {
                    if ($errKey !== 'passage' && $errKey !== 'passage_id') {
                        $errors["question_{$qIdx}_{$errKey}"] = "Question #{$qIdx}: {$errMsg}";
                    }
                }
            }
            $qIdx++;
        }

        return [
            'is_valid'       => empty($errors),
            'valid'          => empty($errors),
            'errors'         => $errors,
            'passage_type'   => $passageType,
            'part_number'    => $partNumber,
            'passage_count'  => $passageCount,
            'question_count' => $questionCount,
        ];
    }

    /**
     * Validate TOEIC Passage Group and throw ValidationException if invalid.
     *
     * @param \App\Modules\QuestionBank\Models\PassageGroup|array<string, mixed> $group
     * @param array<int, mixed> $passagesData
     * @param array<int, mixed> $questionsData
     * @throws ValidationException
     */
    public static function validatePassageGroup(mixed $group, array $passagesData = [], array $questionsData = []): array
    {
        $result = self::checkPassageGroup($group, $passagesData, $questionsData);

        if (!$result['is_valid']) {
            throw ValidationException::withMessages($result['errors']);
        }

        return $result;
    }

    /**
     * Check mathematical feasibility of remaining Single Passage groups given current group and question counts.
     *
     * @param int $currentGroups
     * @param int $currentQuestions
     * @return array{
     *     is_feasible: bool,
     *     reason: string,
     *     message: string,
     *     remaining_groups: int,
     *     remaining_questions: int,
     *     min_possible?: int,
     *     max_possible?: int
     * }
     */
    public static function checkSinglePassageFeasibility(int $currentGroups, int $currentQuestions): array
    {
        $targetGroups = self::TOEIC_PART7_BLUEPRINT['single']['group_count']; // 10
        $targetQuestions = self::TOEIC_PART7_BLUEPRINT['single']['question_total']; // 29

        $remGroups = $targetGroups - $currentGroups;
        $remQuestions = $targetQuestions - $currentQuestions;

        if ($remGroups < 0 || $remQuestions < 0) {
            return [
                'is_feasible'         => false,
                'reason'              => 'overflow',
                'message'             => 'Single Passage count exceeds canonical target (10 groups, 29 questions).',
                'remaining_groups'    => max(0, $remGroups),
                'remaining_questions' => max(0, $remQuestions),
            ];
        }

        if ($remGroups === 0) {
            $feasible = ($remQuestions === 0);
            return [
                'is_feasible'         => $feasible,
                'reason'              => $feasible ? 'exact' : 'mismatch',
                'message'             => $feasible ? 'Single Passage block complete.' : 'All 10 Single Passage groups created but question count is ' . $currentQuestions . '/29.',
                'remaining_groups'    => 0,
                'remaining_questions' => $remQuestions,
            ];
        }

        $minPossible = 2 * $remGroups;
        $maxPossible = 4 * $remGroups;
        $feasible = ($remQuestions >= $minPossible && $remQuestions <= $maxPossible);

        $msg = $feasible
            ? ($remGroups === 1
                ? "Final Single Passage must contain exactly {$remQuestions} questions."
                : "{$remQuestions} questions remaining across {$remGroups} Single Passage groups.")
            : "Remaining {$remQuestions} questions cannot be distributed across {$remGroups} Single Passage groups (2–4 questions required per group; allowed range is {$minPossible}–{$maxPossible} questions).";

        return [
            'is_feasible'         => $feasible,
            'reason'              => $feasible ? 'feasible' : 'impossible',
            'message'             => $msg,
            'remaining_groups'    => $remGroups,
            'remaining_questions' => $remQuestions,
            'min_possible'        => $minPossible,
            'max_possible'        => $maxPossible,
        ];
    }

    /**
     * Evaluate aggregate Part 7 canonical blueprint composition, completeness, ordering, and ranges.
     *
     * @param mixed $groups Iterable of PassageGroup models, arrays, or Test/TestSection
     * @param int $standaloneCount Number of standalone questions not belonging to a passage group
     * @return array<string, mixed>
     */
    public static function evaluatePart7Blueprint(mixed $groups, int $standaloneCount = 0): array
    {
        $blueprint = self::TOEIC_PART7_BLUEPRINT;

        if ($groups instanceof \App\Modules\Assessment\Models\Test || $groups instanceof \App\Modules\QuestionBank\Models\QuestionBank) {
            $groups = \App\Modules\QuestionBank\Models\PassageGroup::where('test_id', (string)$groups->id)
                ->where('part_number', 7)
                ->orderBy('order', 'asc')
                ->orderBy('id', 'asc')
                ->with(['questions.choices', 'passages'])
                ->get();
        } elseif ($groups instanceof \App\Modules\Assessment\Models\TestSection) {
            $groups = \App\Modules\QuestionBank\Models\PassageGroup::where('test_id', (string)$groups->test_id)
                ->where('part_number', 7)
                ->orderBy('order', 'asc')
                ->orderBy('id', 'asc')
                ->with(['questions.choices', 'passages'])
                ->get();
        } elseif ($groups instanceof Collection) {
            // Keep collection
        } elseif (is_array($groups)) {
            $groups = collect($groups);
        } else {
            $groups = collect();
        }

        $singleGroups = [];
        $doubleGroups = [];
        $tripleGroups = [];
        $otherGroups = [];
        $groupSequence = [];
        $allGroupsValid = true;
        $groupIssues = [];

        foreach ($groups as $gIdx => $group) {
            $isModel = $group instanceof \App\Modules\QuestionBank\Models\PassageGroup;
            $type = strtolower((string)($isModel ? $group->passage_type : ($group['passage_type'] ?? 'single')));
            $groupSequence[] = $type;

            // Individual group check
            $pgCheck = self::checkPassageGroup($group);
            if (!$pgCheck['is_valid']) {
                $allGroupsValid = false;
                foreach ($pgCheck['errors'] as $err) {
                    $groupIssues[] = "Passage Group #" . ($gIdx + 1) . " ({$type}): {$err}";
                }
            }

            // Count complete questions
            $completeQuestions = 0;
            if ($isModel) {
                $draftSlots = $group->context_metadata['draft_slots'] ?? null;
                if (is_array($draftSlots) && !empty($draftSlots)) {
                    foreach ($draftSlots as $slot) {
                        if (($slot['state'] ?? '') === 'complete') {
                            $completeQuestions++;
                        }
                    }
                } else {
                    $qs = $group->relationLoaded('questions') ? $group->questions : $group->questions()->get();
                    $completeQuestions = $qs->filter(fn($q) => $q instanceof Question ? $q->isCompleteChild() : !empty($q['prompt']))->count();
                }
            } elseif (is_array($group)) {
                $qs = $group['questions'] ?? [];
                foreach ($qs as $q) {
                    if (is_array($q)) {
                        $hasPrompt = !empty(trim((string)($q['prompt'] ?? '')));
                        $choices = $q['choices'] ?? [];
                        $hasChoices = is_array($choices) && count(array_filter($choices, fn($c) => !empty(trim(is_array($c) ? ($c['content'] ?? $c['choice_text'] ?? '') : (string)$c)))) === 4;
                        $hasCorrect = isset($q['correct_choice']) && $q['correct_choice'] !== '' && $q['correct_choice'] !== null;
                        if ($hasPrompt && $hasChoices && $hasCorrect) {
                            $completeQuestions++;
                        }
                    } elseif ($q instanceof Question) {
                        if ($q->isCompleteChild()) {
                            $completeQuestions++;
                        }
                    }
                }
            }

            $groupData = [
                'model'              => $isModel ? $group : null,
                'type'               => $type,
                'complete_questions' => $completeQuestions,
                'is_valid'           => $pgCheck['is_valid'],
            ];

            match ($type) {
                'single' => $singleGroups[] = $groupData,
                'double' => $doubleGroups[] = $groupData,
                'triple' => $tripleGroups[] = $groupData,
                default  => $otherGroups[] = $groupData,
            };
        }

        $numSingleGroups = count($singleGroups);
        $numSingleQuestions = array_sum(array_column($singleGroups, 'complete_questions'));

        $numDoubleGroups = count($doubleGroups);
        $numDoubleQuestions = array_sum(array_column($doubleGroups, 'complete_questions'));

        $numTripleGroups = count($tripleGroups);
        $numTripleQuestions = array_sum(array_column($tripleGroups, 'complete_questions'));

        $totalGroups = count($groups);
        $totalQuestions = $numSingleQuestions + $numDoubleQuestions + $numTripleQuestions + array_sum(array_column($otherGroups, 'complete_questions')) + $standaloneCount;

        // Single evaluation
        $singleExact = ($numSingleGroups === $blueprint['single']['group_count'] && $numSingleQuestions === $blueprint['single']['question_total']);
        $singleFeasibility = self::checkSinglePassageFeasibility($numSingleGroups, $numSingleQuestions);

        // Double evaluation
        $doubleExact = ($numDoubleGroups === $blueprint['double']['group_count'] && $numDoubleQuestions === $blueprint['double']['question_total']);
        $doubleLocked = !$singleExact;

        // Triple evaluation
        $tripleExact = ($numTripleGroups === $blueprint['triple']['group_count'] && $numTripleQuestions === $blueprint['triple']['question_total']);
        $tripleLocked = !$singleExact || !$doubleExact;

        // Ordering validation (Single -> Double -> Triple)
        $orderingValid = true;
        $orderingMessage = null;
        $seenDouble = false;
        $seenTriple = false;

        foreach ($groupSequence as $seqType) {
            if ($seqType === 'single') {
                if ($seenDouble || $seenTriple) {
                    $orderingValid = false;
                    break;
                }
            } elseif ($seqType === 'double') {
                if ($seenTriple) {
                    $orderingValid = false;
                    break;
                }
                $seenDouble = true;
            } elseif ($seqType === 'triple') {
                $seenTriple = true;
            } else {
                $orderingValid = false;
                break;
            }
        }

        if (!$orderingValid) {
            $orderingMessage = "Part 7 passage groups are out of canonical order. Expected Single → Double → Triple.";
        }

        // Ready check
        $isReady = $singleExact
            && $doubleExact
            && $tripleExact
            && $totalGroups === $blueprint['total_groups']
            && $totalQuestions === $blueprint['total_questions']
            && $standaloneCount === 0
            && $orderingValid
            && $allGroupsValid
            && empty($otherGroups);

        // Build findings for Validation Assistant
        $findings = [];
        if ($standaloneCount > 0) {
            $findings[] = "Part 7 Reading Comprehension requires all questions to belong to passage groups; {$standaloneCount} standalone question(s) found.";
        }
        if (!$orderingValid && $orderingMessage) {
            $findings[] = $orderingMessage;
        }
        foreach ($groupIssues as $gi) {
            $findings[] = $gi;
        }

        if (!$singleExact) {
            if (!$singleFeasibility['is_feasible']) {
                $findings[] = "Part 7 Single Passage question distribution is impossible ({$numSingleQuestions}/29 questions in {$numSingleGroups}/10 groups).";
            } elseif ($numSingleGroups > $blueprint['single']['group_count']) {
                $findings[] = "Part 7 Single Passage exceeds 10 groups limit ({$numSingleGroups}/10 groups).";
            } elseif ($numSingleQuestions > $blueprint['single']['question_total']) {
                $findings[] = "Part 7 Single Passage exceeds 29 questions limit ({$numSingleQuestions}/29 questions).";
            } else {
                $remG = $blueprint['single']['group_count'] - $numSingleGroups;
                $remQ = $blueprint['single']['question_total'] - $numSingleQuestions;
                $findings[] = "Part 7 Single Passage: {$numSingleGroups} / 10 groups, {$numSingleQuestions} / 29 questions ({$remQ} questions remaining across {$remG} groups).";
            }
        }

        if ($singleExact && !$doubleExact) {
            if ($numDoubleGroups > $blueprint['double']['group_count']) {
                $findings[] = "Part 7 Double Passage exceeds 2 groups limit ({$numDoubleGroups}/2 groups).";
            } elseif ($numDoubleQuestions > $blueprint['double']['question_total']) {
                $findings[] = "Part 7 Double Passage exceeds 10 questions limit ({$numDoubleQuestions}/10 questions).";
            } else {
                $findings[] = "Part 7 Double Passage: {$numDoubleGroups} / 2 groups, {$numDoubleQuestions} / 10 questions.";
            }
        } elseif (!$singleExact && ($numDoubleGroups > 0 || $numDoubleQuestions > 0)) {
            $findings[] = "Part 7 Double Passage is locked until Single Passage reaches 10 groups / 29 questions.";
        }

        if ($singleExact && $doubleExact && !$tripleExact) {
            if ($numTripleGroups > $blueprint['triple']['group_count']) {
                $findings[] = "Part 7 Triple Passage exceeds 3 groups limit ({$numTripleGroups}/3 groups).";
            } elseif ($numTripleQuestions > $blueprint['triple']['question_total']) {
                $findings[] = "Part 7 Triple Passage exceeds 15 questions limit ({$numTripleQuestions}/15 questions).";
            } else {
                $findings[] = "Part 7 Triple Passage: {$numTripleGroups} / 3 groups, {$numTripleQuestions} / 15 questions.";
            }
        } elseif ((!$singleExact || !$doubleExact) && ($numTripleGroups > 0 || $numTripleQuestions > 0)) {
            if (!$singleExact) {
                $findings[] = "Part 7 Triple Passage is locked until Single Passage reaches 10 groups / 29 questions.";
            } else {
                $findings[] = "Part 7 Triple Passage is locked until Double Passage reaches 2 groups / 10 questions.";
            }
        }

        if ($totalQuestions === 54 && !$isReady) {
            $findings[] = "Part 7 contains 54 questions but its passage-type composition does not match the canonical TOEIC blueprint.";
        }

        return [
            'total' => [
                'groups'             => $totalGroups,
                'questions'          => $totalQuestions,
                'expected_groups'    => $blueprint['total_groups'],
                'expected_questions' => $blueprint['total_questions'],
                'status'             => $isReady ? 'ready' : 'needs_attention',
            ],
            'single' => [
                'groups'              => $numSingleGroups,
                'questions'           => $numSingleQuestions,
                'expected_groups'     => $blueprint['single']['group_count'],
                'expected_questions'  => $blueprint['single']['question_total'],
                'remaining_groups'    => max(0, $blueprint['single']['group_count'] - $numSingleGroups),
                'remaining_questions' => max(0, $blueprint['single']['question_total'] - $numSingleQuestions),
                'is_exact'            => $singleExact,
                'is_feasible'         => $singleFeasibility['is_feasible'],
                'feasibility_reason'  => $singleFeasibility['reason'] ?? 'feasible',
                'feasibility_message' => $singleFeasibility['message'] ?? '',
                'range'               => ['start' => 147, 'end' => 175],
                'status'              => $singleExact ? 'complete' : (!$singleFeasibility['is_feasible'] || $numSingleGroups > 10 || $numSingleQuestions > 29 ? 'invalid' : ($numSingleGroups > 0 ? 'in_progress' : 'not_started')),
            ],
            'double' => [
                'groups'              => $numDoubleGroups,
                'questions'           => $numDoubleQuestions,
                'expected_groups'     => $blueprint['double']['group_count'],
                'expected_questions'  => $blueprint['double']['question_total'],
                'remaining_groups'    => max(0, $blueprint['double']['group_count'] - $numDoubleGroups),
                'remaining_questions' => max(0, $blueprint['double']['question_total'] - $numDoubleQuestions),
                'is_exact'            => $doubleExact,
                'is_locked'           => $doubleLocked,
                'range'               => ['start' => 176, 'end' => 185],
                'status'              => $doubleExact ? 'complete' : ($numDoubleGroups > 2 || $numDoubleQuestions > 10 ? 'invalid' : ($doubleLocked ? 'locked' : ($numDoubleGroups > 0 ? 'in_progress' : 'not_started'))),
            ],
            'triple' => [
                'groups'              => $numTripleGroups,
                'questions'           => $numTripleQuestions,
                'expected_groups'     => $blueprint['triple']['group_count'],
                'expected_questions'  => $blueprint['triple']['question_total'],
                'remaining_groups'    => max(0, $blueprint['triple']['group_count'] - $numTripleGroups),
                'remaining_questions' => max(0, $blueprint['triple']['question_total'] - $numTripleQuestions),
                'is_exact'            => $tripleExact,
                'is_locked'           => $tripleLocked,
                'range'               => ['start' => 186, 'end' => 200],
                'status'              => $tripleExact ? 'complete' : ($numTripleGroups > 3 || $numTripleQuestions > 15 ? 'invalid' : ($tripleLocked ? 'locked' : ($numTripleGroups > 0 ? 'in_progress' : 'not_started'))),
            ],
            'ordering' => [
                'valid'   => $orderingValid,
                'message' => $orderingMessage,
            ],
            'is_ready'  => $isReady,
            'findings'  => array_values(array_unique($findings)),
        ];
    }

    /**
     * Validate aggregate creation or update of a Part 7 Passage Group against canonical limits and prerequisites.
     *
     * @param mixed $existingGroups
     * @param string $proposedPassageType
     * @param int $proposedQuestionCount
     * @param \App\Modules\QuestionBank\Models\PassageGroup|null $updatingGroup
     * @throws ValidationException
     */
    public static function validatePart7AggregateCreation(
        mixed $existingGroups,
        string $proposedPassageType,
        int $proposedQuestionCount = 0,
        ?\App\Modules\QuestionBank\Models\PassageGroup $updatingGroup = null
    ): void {
        $blueprint = self::TOEIC_PART7_BLUEPRINT;
        $proposedType = strtolower($proposedPassageType);

        if ($updatingGroup && $existingGroups instanceof Collection) {
            $existingGroups = $existingGroups->filter(fn($g) => (string)$g->id !== (string)$updatingGroup->id);
        }

        $eval = self::evaluatePart7Blueprint($existingGroups);

        $isChangingType = $updatingGroup ? (strtolower((string)$updatingGroup->passage_type) !== $proposedType) : false;
        $isNew = is_null($updatingGroup);

        if ($proposedType === 'single') {
            $targetGroups = $blueprint['single']['group_count']; // 10
            $targetQuestions = $blueprint['single']['question_total']; // 29

            $newGroupCount = $eval['single']['groups'] + 1;
            if ($newGroupCount > $targetGroups) {
                throw ValidationException::withMessages([
                    'passage_type' => ["Cannot exceed {$targetGroups} Single Passage groups in Part 7 (currently {$eval['single']['groups']}/{$targetGroups})."],
                ]);
            }

            $newQuestionTotal = $eval['single']['questions'] + $proposedQuestionCount;
            if ($newQuestionTotal > $targetQuestions) {
                throw ValidationException::withMessages([
                    'questions' => ["Cannot exceed {$targetQuestions} Single Passage questions in Part 7 (adding {$proposedQuestionCount} question(s) would reach {$newQuestionTotal}/{$targetQuestions})."],
                ]);
            }

            $feas = self::checkSinglePassageFeasibility($newGroupCount, $newQuestionTotal);
            if (!$feas['is_feasible']) {
                throw ValidationException::withMessages([
                    'questions' => ["Single Passage question distribution would be impossible to complete: {$feas['message']}"],
                ]);
            }
        } elseif ($proposedType === 'double') {
            $targetGroups = $blueprint['double']['group_count']; // 2
            $targetQuestions = $blueprint['double']['question_total']; // 10

            if (($isNew || $isChangingType) && !$eval['single']['is_exact']) {
                throw ValidationException::withMessages([
                    'passage_type' => ["Double Passage creation is locked until the Single Passage block is complete (requires 10 groups and 29 questions; currently {$eval['single']['groups']}/10 groups, {$eval['single']['questions']}/29 questions)."],
                ]);
            }

            $newGroupCount = $eval['double']['groups'] + 1;
            if ($newGroupCount > $targetGroups) {
                throw ValidationException::withMessages([
                    'passage_type' => ["Cannot exceed {$targetGroups} Double Passage groups in Part 7 (currently {$eval['double']['groups']}/{$targetGroups})."],
                ]);
            }

            $newQuestionTotal = $eval['double']['questions'] + $proposedQuestionCount;
            if ($newQuestionTotal > $targetQuestions) {
                throw ValidationException::withMessages([
                    'questions' => ["Cannot exceed {$targetQuestions} Double Passage questions in Part 7 (adding {$proposedQuestionCount} question(s) would reach {$newQuestionTotal}/{$targetQuestions})."],
                ]);
            }
        } elseif ($proposedType === 'triple') {
            $targetGroups = $blueprint['triple']['group_count']; // 3
            $targetQuestions = $blueprint['triple']['question_total']; // 15

            if ($isNew || $isChangingType) {
                if (!$eval['single']['is_exact']) {
                    throw ValidationException::withMessages([
                        'passage_type' => ["Triple Passage creation is locked until Single and Double Passage blocks are complete (Single requires 10 groups and 29 questions; currently {$eval['single']['groups']}/10 groups, {$eval['single']['questions']}/29 questions)."],
                    ]);
                }
                if (!$eval['double']['is_exact']) {
                    throw ValidationException::withMessages([
                        'passage_type' => ["Triple Passage creation is locked until the Double Passage block is complete (requires 2 groups and 10 questions; currently {$eval['double']['groups']}/2 groups, {$eval['double']['questions']}/10 questions)."],
                    ]);
                }
            }

            $newGroupCount = $eval['triple']['groups'] + 1;
            if ($newGroupCount > $targetGroups) {
                throw ValidationException::withMessages([
                    'passage_type' => ["Cannot exceed {$targetGroups} Triple Passage groups in Part 7 (currently {$eval['triple']['groups']}/{$targetGroups})."],
                ]);
            }

            $newQuestionTotal = $eval['triple']['questions'] + $proposedQuestionCount;
            if ($newQuestionTotal > $targetQuestions) {
                throw ValidationException::withMessages([
                    'questions' => ["Cannot exceed {$targetQuestions} Triple Passage questions in Part 7 (adding {$proposedQuestionCount} question(s) would reach {$newQuestionTotal}/{$targetQuestions})."],
                ]);
            }
        }
    }
}
