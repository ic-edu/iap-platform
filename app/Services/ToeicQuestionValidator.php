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
        if ($partNumber !== 2 && empty(trim((string) $prompt))) {
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

                if ($partNumber === 2) {
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
            if ($partNumber === 2) {
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
        $hasImage = !empty($data['image_url']);
        $hasAudio = !empty($data['audio_url']);
        $hasPassage = !empty($data['passage_id']) || !empty(trim((string) ($data['passage_text'] ?? '')));

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
            if (!$hasImage && !empty($question->image_url)) {
                $hasImage = true;
            }
            if (!$hasAudio && !empty($question->audio_url)) {
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
        $validDocTypes = ['email', 'memo', 'notice', 'advertisement', 'article', 'letter', 'chat', 'schedule', 'other'];
        $pIdx = 1;
        foreach ($pList as $pItem) {
            $pData = is_array($pItem) ? $pItem : $pItem->toArray();
            $content = $pData['content'] ?? ($pData['passage_text'] ?? '');
            if (empty(trim((string) $content))) {
                $errors["passage_{$pIdx}_content"] = "Passage #{$pIdx} content cannot be empty.";
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
}
