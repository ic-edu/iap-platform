<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Models\Question;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class QuestionDifficultyDetectionService
{
    /**
     * Advanced vocabulary indicators for TOEIC Part 1, Part 5, Part 6, Part 7.
     */
    protected static array $advancedVocab = [
        'accommodate', 'adjacent', 'apparel', 'appliance', 'assembled', 'auditorium',
        'authorization', 'commute', 'compensation', 'comprehensive', 'concourse',
        'confidential', 'consecutive', 'contingent', 'designated', 'deteriorate',
        'discrepancy', 'dismantle', 'eligible', 'entitled', 'equilibrium',
        'exponentially', 'feasibility', 'fluctuation', 'foliage', 'hazardous',
        'horizontal', 'illuminated', 'implement', 'inaugural', 'incentive',
        'incorporate', 'indispensable', 'infrastructure', 'initiative', 'inspection',
        'installation', 'intersection', 'inventory', 'itinerary', 'mandatory',
        'merchandise', 'monument', 'negligible', 'negotiation', 'obligation',
        'occupant', 'optimization', 'orientation', 'partition', 'patron',
        'pedestrian', 'perishable', 'perspective', 'platform', 'precaution',
        'preliminary', 'prerequisite', 'privilege', 'procurement', 'proficient',
        'prohibit', 'prominently', 'prospective', 'provisional', 'punctual',
        'quadrant', 'qualification', 'questionnaire', 'quota', 'reconciliation',
        'reconfiguration', 'rectify', 'redundant', 'refurbish', 'reimbursement',
        'reluctance', 'renovation', 'replenish', 'scaffolding', 'solicit',
        'specifications', 'stanchion', 'stationary', 'stipulation', 'stringent',
        'subcontractor', 'subscription', 'subsequent', 'subsidiary', 'supplementary',
        'surplus', 'suspended', 'surveillance', 'sustainable', 'tentatively',
        'therapeutic', 'thoroughly', 'transaction', 'transmission', 'transparency',
        'unanimous', 'underestimate', 'underwrite', 'unprecedented', 'unveil',
        'utilization', 'verification', 'versatile', 'veteran', 'vicinity',
        'warehouse', 'warranty', 'waterfront', 'withstand', 'workforce',
    ];

    /**
     * Transition / Complex connective indicators for Part 5 / 6.
     */
    protected static array $complexTransitions = [
        'in accordance with', 'contingent upon', 'prior to', 'in addition to',
        'on behalf of', 'with respect to', 'notwithstanding', 'subsequently',
        'furthermore', 'nevertheless', 'consequently', 'alternatively',
        'meanwhile', 'furthermore', 'inasmuch as', 'provided that',
        'as long as', 'whereas', 'despite the fact that', 'in the event of',
    ];

    /**
     * Detect difficulty from input data and/or existing question model.
     *
     * @param array<string, mixed> $data
     * @param Question|null $question
     * @return array{
     *     difficulty_level: string,
     *     difficulty_score: int,
     *     difficulty_status: string,
     *     difficulty_source: string,
     *     difficulty_factors: array<string, mixed>,
     *     difficulty_detected_at: Carbon
     * }
     */
    public static function detect(array $data, ?Question $question = null): array
    {
        $prompt = trim((string) ($data['prompt'] ?? ($question?->prompt ?? '')));
        $partNumber = isset($data['part_number']) && $data['part_number'] !== ''
            ? (int) $data['part_number']
            : ($question?->part_number ?? null);
        $section = (string) ($data['section'] ?? ($question?->section?->value ?? ($question?->section ?? '')));
        $qType = (string) ($data['question_type'] ?? ($question?->question_type?->value ?? ($question?->question_type ?? 'multiple_choice')));

        // Resolve Choices
        $choicesInfo = self::extractChoices($data, $question);
        $choices = $choicesInfo['choices'];
        $correctIndex = $choicesInfo['correct_index'];

        // Resolve Media & Passage
        $mediaInfo = self::extractMedia($data, $question);
        $hasImage = $mediaInfo['has_image'];
        $hasAudio = $mediaInfo['has_audio'];
        $passageText = $mediaInfo['passage_text'];
        $hasPassage = !empty($passageText) || !empty($mediaInfo['passage_id']) || $mediaInfo['has_passage_group'];

        // Determine if TOEIC or generic
        $isToeic = !empty($partNumber) || ToeicQuestionValidator::isToeic($question) || ToeicQuestionValidator::isToeic($data);

        if ($isToeic && !empty($partNumber)) {
            $detection = match ((int) $partNumber) {
                1 => self::detectPart1($prompt, $choices, $correctIndex, $hasImage, $hasAudio),
                2 => self::detectPart2($prompt, $choices, $correctIndex, $hasAudio),
                3, 4 => self::detectPart3Or4($prompt, $choices, $correctIndex, $hasAudio, (int) $partNumber),
                5 => self::detectPart5($prompt, $choices, $correctIndex),
                6 => self::detectPart6($prompt, $choices, $correctIndex, $passageText),
                7 => self::detectPart7($prompt, $choices, $correctIndex, $passageText, $data),
                default => self::detectGeneric($prompt, $choices, $hasImage, $hasAudio, $hasPassage),
            };
        } else {
            $detection = self::detectGeneric($prompt, $choices, $hasImage, $hasAudio, $hasPassage);
        }

        $level = $detection['difficulty_level'];
        $score = max(0, min(100, (int) round($detection['difficulty_score'])));
        $status = $detection['difficulty_status'];
        $factors = $detection['difficulty_factors'];

        return [
            'difficulty_level'       => $level,
            'difficulty_score'       => $score,
            'difficulty_status'      => $status,
            'difficulty_source'      => 'auto',
            'difficulty_factors'     => $factors,
            'difficulty_detected_at' => Carbon::now(),
        ];
    }

    /**
     * TOEIC PART 1 — Photographs
     */
    protected static function detectPart1(
        string $prompt,
        array $choices,
        mixed $correctIndex,
        bool $hasImage,
        bool $hasAudio
    ): array {
        $factors = [
            'part'           => 1,
            'part_name'      => 'Photographs',
            'has_image'      => $hasImage,
            'has_audio'      => $hasAudio,
            'choices_count'  => count($choices),
            'missing_inputs' => [],
        ];

        if (!$hasImage) $factors['missing_inputs'][] = 'image';
        if (!$hasAudio) $factors['missing_inputs'][] = 'audio';
        if (count($choices) < 4) $factors['missing_inputs'][] = 'choices (4 required)';

        // Lifecycle State
        if (empty($prompt) && empty($choices) && !$hasImage && !$hasAudio) {
            return [
                'difficulty_level'   => 'medium',
                'difficulty_score'   => 50,
                'difficulty_status'  => 'pending',
                'difficulty_factors' => array_merge($factors, ['reason' => 'Waiting for photograph, audio, or options']),
            ];
        }

        $isComplete = $hasImage && $hasAudio && count($choices) === 4;
        $status = $isComplete ? 'final' : 'provisional';

        // Base score for Part 1 items
        $score = 35;
        $reasons = [];

        // Choice length analysis
        $totalWords = 0;
        $advancedWordCount = 0;
        $hasComplexStructure = false;

        foreach ($choices as $choiceText) {
            $words = str_word_count(strtolower($choiceText), 1);
            $totalWords += count($words);

            foreach ($words as $w) {
                if (in_array($w, self::$advancedVocab, true)) {
                    $advancedWordCount++;
                }
            }

            if (preg_match('/(suspended|arranged|stacked|positioned|installed|displayed|fastened|unloading|boarding|patronizing)/i', $choiceText)) {
                $hasComplexStructure = true;
            }
        }

        $avgWords = count($choices) > 0 ? $totalWords / count($choices) : 0;

        if ($avgWords <= 6.5 && $advancedWordCount === 0) {
            $score -= 10;
            $reasons[] = 'Short, direct action statements (e.g. basic present continuous)';
        } elseif ($avgWords >= 8 || $advancedWordCount >= 2 || $hasComplexStructure) {
            $score += 35;
            $reasons[] = 'Complex scene description, passive voice, or specific spatial vocabulary';
        } else {
            $score += 15;
            $reasons[] = 'Standard descriptive statements with intermediate vocabulary';
        }

        $factors['avg_choice_words'] = round($avgWords, 1);
        $factors['advanced_words_found'] = $advancedWordCount;
        $factors['reasons'] = $reasons;

        $level = self::mapScoreToLevel($score);

        return [
            'difficulty_level'   => $level,
            'difficulty_score'   => $score,
            'difficulty_status'  => $status,
            'difficulty_factors' => $factors,
        ];
    }

    /**
     * TOEIC PART 2 — Question-Response
     */
    protected static function detectPart2(
        string $prompt,
        array $choices,
        mixed $correctIndex,
        bool $hasAudio
    ): array {
        $factors = [
            'part'           => 2,
            'part_name'      => 'Question-Response',
            'has_audio'      => $hasAudio,
            'choices_count'  => count($choices),
            'missing_inputs' => [],
        ];

        if (!$hasAudio) $factors['missing_inputs'][] = 'audio';
        if (count($choices) < 3) $factors['missing_inputs'][] = 'choices (3 required)';

        if (empty($prompt) && empty($choices) && !$hasAudio) {
            return [
                'difficulty_level'   => 'medium',
                'difficulty_score'   => 50,
                'difficulty_status'  => 'pending',
                'difficulty_factors' => array_merge($factors, ['reason' => 'Waiting for audio prompt or response options']),
            ];
        }

        $isComplete = count($choices) === 3 && (!empty($prompt) || $hasAudio);
        $status = $isComplete ? 'final' : 'provisional';

        $score = 45;
        $reasons = [];
        $lowerPrompt = strtolower($prompt);

        // Prompt type classification
        if (preg_match('/^(when|where|who|what time|how many|how much)\b/i', $lowerPrompt)) {
            $score -= 15;
            $reasons[] = 'Direct factual Wh-question prompt';
        } elseif (preg_match('/^(why don\'t|how about|would you like|could you|is there|do you know)\b/i', $lowerPrompt)) {
            $score += 5;
            $reasons[] = 'Polite request, suggestion, or indirect question prompt';
        } elseif (preg_match('/\?$/', $prompt) === 0 || preg_match('/^(i think|i was wondering|the shipment|we need to|didn\'t you|haven\'t you)/i', $lowerPrompt)) {
            $score += 30;
            $reasons[] = 'Statement / opinion / negative question requiring pragmatic inference';
        }

        // Response analysis (indirect responses)
        $hasIndirectResponse = false;
        foreach ($choices as $choiceText) {
            if (preg_match('/(actually|i haven\'t|check with|ask |not until|it depends|postponed|cancelled|already)/i', $choiceText)) {
                $hasIndirectResponse = true;
            }
        }

        if ($hasIndirectResponse) {
            $score += 15;
            $reasons[] = 'Indirect conversational or non-literal answer choice';
        }

        $factors['reasons'] = $reasons;
        $level = self::mapScoreToLevel($score);

        return [
            'difficulty_level'   => $level,
            'difficulty_score'   => $score,
            'difficulty_status'  => $status,
            'difficulty_factors' => $factors,
        ];
    }

    /**
     * TOEIC PART 3 & PART 4 — Conversations & Talks
     */
    protected static function detectPart3Or4(
        string $prompt,
        array $choices,
        mixed $correctIndex,
        bool $hasAudio,
        int $partNumber
    ): array {
        $factors = [
            'part'           => $partNumber,
            'part_name'      => $partNumber === 3 ? 'Conversations' : 'Talks',
            'has_audio'      => $hasAudio,
            'choices_count'  => count($choices),
            'missing_inputs' => [],
        ];

        if (!$hasAudio) $factors['missing_inputs'][] = 'audio';
        if (count($choices) < 4) $factors['missing_inputs'][] = 'choices (4 required)';

        if (empty($prompt) && empty($choices) && !$hasAudio) {
            return [
                'difficulty_level'   => 'medium',
                'difficulty_score'   => 50,
                'difficulty_status'  => 'pending',
                'difficulty_factors' => array_merge($factors, ['reason' => 'Waiting for stem, choices, or audio']),
            ];
        }

        $isComplete = count($choices) === 4 && !empty($prompt);
        $status = $isComplete ? 'final' : 'provisional';

        $score = 48;
        $reasons = [];
        $lowerPrompt = strtolower($prompt);

        // Question stem depth
        if (preg_match('/(what is the problem|where does the|what time|what does the man want)/i', $lowerPrompt)) {
            $score -= 10;
            $reasons[] = 'Direct factual / explicitly stated detail question';
        } elseif (preg_match('/(who most likely is|what is the conversation mainly about|why is the speaker calling)/i', $lowerPrompt)) {
            $score += 5;
            $reasons[] = 'Main purpose or speaker identification question';
        } elseif (preg_match('/(imply|suggest|probably do next|most likely happen|look at the graphic|mean when)/i', $lowerPrompt)) {
            $score += 28;
            $reasons[] = 'Inference, quotation implication, or graphic interpretation question';
        }

        // Choice length / complexity
        $totalWords = 0;
        foreach ($choices as $c) {
            $totalWords += str_word_count($c);
        }
        $avgWords = count($choices) > 0 ? $totalWords / count($choices) : 0;
        if ($avgWords >= 7) {
            $score += 10;
            $reasons[] = 'Longer, full-clause answer options';
        }

        $factors['reasons'] = $reasons;
        $level = self::mapScoreToLevel($score);

        return [
            'difficulty_level'   => $level,
            'difficulty_score'   => $score,
            'difficulty_status'  => $status,
            'difficulty_factors' => $factors,
        ];
    }

    /**
     * TOEIC PART 5 — Incomplete Sentences
     */
    protected static function detectPart5(
        string $prompt,
        array $choices,
        mixed $correctIndex
    ): array {
        $factors = [
            'part'           => 5,
            'part_name'      => 'Incomplete Sentences',
            'choices_count'  => count($choices),
            'missing_inputs' => [],
        ];

        if (empty($prompt)) $factors['missing_inputs'][] = 'prompt sentence';
        if (count($choices) < 4) $factors['missing_inputs'][] = 'choices (4 required)';

        if (empty($prompt) && empty($choices)) {
            return [
                'difficulty_level'   => 'medium',
                'difficulty_score'   => 50,
                'difficulty_status'  => 'pending',
                'difficulty_factors' => array_merge($factors, ['reason' => 'Waiting for sentence prompt and choices']),
            ];
        }

        $status = (!empty($prompt) && count($choices) === 4) ? 'final' : 'provisional';
        $score = 42;
        $reasons = [];

        $promptWords = str_word_count(strtolower($prompt), 1);
        $promptWordCount = count($promptWords);

        // 1. Vocabulary & Grammar Structure Checks
        $hasComplexTransition = false;
        foreach (self::$complexTransitions as $transition) {
            if (stripos($prompt, $transition) !== false) {
                $hasComplexTransition = true;
                break;
            }
        }

        $advancedVocabCount = 0;
        foreach ($promptWords as $w) {
            if (in_array($w, self::$advancedVocab, true)) {
                $advancedVocabCount++;
            }
        }

        foreach ($choices as $c) {
            $cWords = str_word_count(strtolower($c), 1);
            foreach ($cWords as $cw) {
                if (in_array($cw, self::$advancedVocab, true)) {
                    $advancedVocabCount++;
                }
            }
        }

        // 2. Choice Morphology (Grammar vs Lexical discrimination)
        $isPronounOrBasicForm = false;
        if (count($choices) >= 4) {
            $c0 = strtolower($choices[0]);
            $c1 = strtolower($choices[1]);
            // Common pronoun or identical root check
            if (preg_match('/^(he|him|his|himself|she|her|hers|herself|they|them|their|themselves|who|whom|whose|which)$/i', $c0) &&
                preg_match('/^(he|him|his|himself|she|her|hers|herself|they|them|their|themselves|who|whom|whose|which)$/i', $c1)) {
                $isPronounOrBasicForm = true;
            }
        }

        if ($isPronounOrBasicForm) {
            $score -= 18;
            $reasons[] = 'Basic pronoun / grammatical form distinction';
        } elseif ($hasComplexTransition || $advancedVocabCount >= 3) {
            $score += 35;
            $reasons[] = 'Advanced business collocation / complex subordination structure';
        } elseif ($advancedVocabCount >= 1 || $promptWordCount >= 20) {
            $score += 15;
            $reasons[] = 'Intermediate vocabulary discrimination or multi-clause sentence';
        } else {
            $score -= 8;
            $reasons[] = 'Standard vocabulary and single-clause grammatical pattern';
        }

        $factors['prompt_words'] = $promptWordCount;
        $factors['advanced_vocab_count'] = $advancedVocabCount;
        $factors['reasons'] = $reasons;

        $level = self::mapScoreToLevel($score);

        return [
            'difficulty_level'   => $level,
            'difficulty_score'   => $score,
            'difficulty_status'  => $status,
            'difficulty_factors' => $factors,
        ];
    }

    /**
     * TOEIC PART 6 — Text Completion
     */
    protected static function detectPart6(
        string $prompt,
        array $choices,
        mixed $correctIndex,
        ?string $passageText
    ): array {
        $factors = [
            'part'           => 6,
            'part_name'      => 'Text Completion',
            'has_passage'    => !empty($passageText),
            'choices_count'  => count($choices),
            'missing_inputs' => [],
        ];

        if (empty($passageText)) $factors['missing_inputs'][] = 'passage text';
        if (count($choices) < 4) $factors['missing_inputs'][] = 'choices (4 required)';

        if (empty($passageText) && empty($prompt) && empty($choices)) {
            return [
                'difficulty_level'   => 'medium',
                'difficulty_score'   => 50,
                'difficulty_status'  => 'pending',
                'difficulty_factors' => array_merge($factors, ['reason' => 'Waiting for passage and choices']),
            ];
        }

        $status = (!empty($passageText) && count($choices) === 4) ? 'final' : 'provisional';
        $score = 48;
        $reasons = [];

        // Check if question is a sentence-insertion item (whole sentence in choices)
        $avgChoiceWords = 0;
        $totalChoiceWords = 0;
        foreach ($choices as $c) {
            $totalChoiceWords += str_word_count($c);
        }
        if (count($choices) > 0) {
            $avgChoiceWords = $totalChoiceWords / count($choices);
        }

        if ($avgChoiceWords >= 6) {
            $score += 28;
            $reasons[] = 'Whole-sentence insertion item requiring context flow synthesis';
        } else {
            $score += 5;
            $reasons[] = 'Passage-embedded vocabulary / transitional blank';
        }

        $factors['reasons'] = $reasons;
        $level = self::mapScoreToLevel($score);

        return [
            'difficulty_level'   => $level,
            'difficulty_score'   => $score,
            'difficulty_status'  => $status,
            'difficulty_factors' => $factors,
        ];
    }

    /**
     * TOEIC PART 7 — Reading Comprehension
     */
    protected static function detectPart7(
        string $prompt,
        array $choices,
        mixed $correctIndex,
        ?string $passageText,
        array $data
    ): array {
        $passageType = $data['passage_type'] ?? 'single';
        $factors = [
            'part'           => 7,
            'part_name'      => 'Reading Comprehension',
            'passage_type'   => $passageType,
            'has_passage'    => !empty($passageText),
            'choices_count'  => count($choices),
            'missing_inputs' => [],
        ];

        if (empty($passageText) && empty($data['passage_id']) && empty($data['passage_group_id'])) {
            $factors['missing_inputs'][] = 'passage text';
        }
        if (count($choices) < 4) $factors['missing_inputs'][] = 'choices (4 required)';

        if (empty($passageText) && empty($prompt) && empty($choices)) {
            return [
                'difficulty_level'   => 'medium',
                'difficulty_score'   => 50,
                'difficulty_status'  => 'pending',
                'difficulty_factors' => array_merge($factors, ['reason' => 'Waiting for reading passage, stem, or choices']),
            ];
        }

        $status = (!empty($passageText) && !empty($prompt) && count($choices) === 4) ? 'final' : 'provisional';
        $score = 50;
        $reasons = [];

        // Multi-passage bonus
        if ($passageType === 'double') {
            $score += 15;
            $reasons[] = 'Double passage cross-referencing requirement';
        } elseif ($passageType === 'triple') {
            $score += 25;
            $reasons[] = 'Triple passage synthesis requirement';
        }

        $lowerPrompt = strtolower($prompt);

        if (preg_match('/(what is suggested|what is implied|most likely|what can be inferred)/i', $lowerPrompt)) {
            $score += 20;
            $reasons[] = 'Implicit inference / deductive reasoning stem';
        } elseif (preg_match('/(not mentioned|not true|not indicated|except)/i', $lowerPrompt)) {
            $score += 18;
            $reasons[] = 'Negative fact verification across entire passage';
        } elseif (preg_match('/(closest in meaning to|in line)/i', $lowerPrompt)) {
            $score += 12;
            $reasons[] = 'Vocabulary-in-context lexical nuance';
        } elseif (preg_match('/(what time|what date|how much|where will)/i', $lowerPrompt)) {
            $score -= 15;
            $reasons[] = 'Direct scanning / explicit factual detail';
        }

        $factors['reasons'] = $reasons;
        $level = self::mapScoreToLevel($score);

        return [
            'difficulty_level'   => $level,
            'difficulty_score'   => $score,
            'difficulty_status'  => $status,
            'difficulty_factors' => $factors,
        ];
    }

    /**
     * Generic fallback detection for non-TOEIC or unclassified assessments.
     */
    protected static function detectGeneric(
        string $prompt,
        array $choices,
        bool $hasImage,
        bool $hasAudio,
        bool $hasPassage
    ): array {
        $factors = [
            'has_image'      => $hasImage,
            'has_audio'      => $hasAudio,
            'has_passage'    => $hasPassage,
            'choices_count'  => count($choices),
            'missing_inputs' => [],
        ];

        if (empty($prompt) && empty($choices)) {
            return [
                'difficulty_level'   => 'medium',
                'difficulty_score'   => 50,
                'difficulty_status'  => 'pending',
                'difficulty_factors' => array_merge($factors, ['reason' => 'Waiting for question prompt']),
            ];
        }

        $status = (!empty($prompt) && count($choices) >= 2) ? 'final' : 'provisional';
        $score = 45;
        $reasons = [];

        $wordCount = str_word_count($prompt);
        if ($wordCount <= 8) {
            $score -= 10;
            $reasons[] = 'Short stem length';
        } elseif ($wordCount >= 30) {
            $score += 15;
            $reasons[] = 'Longer reading stem';
        }

        if ($hasPassage) {
            $score += 12;
            $reasons[] = 'Passage-based reading item';
        }
        if ($hasAudio) {
            $score += 8;
            $reasons[] = 'Audio listening component';
        }

        $factors['reasons'] = $reasons;
        $level = self::mapScoreToLevel($score);

        return [
            'difficulty_level'   => $level,
            'difficulty_score'   => $score,
            'difficulty_status'  => $status,
            'difficulty_factors' => $factors,
        ];
    }

    /**
     * Map numeric score (0..100) to canonical difficulty level.
     */
    public static function mapScoreToLevel(int $score): string
    {
        if ($score < 40) {
            return 'easy';
        }
        if ($score >= 70) {
            return 'hard';
        }
        return 'medium';
    }

    /**
     * Helper to extract choices from array or model.
     */
    protected static function extractChoices(array $data, ?Question $question = null): array
    {
        $choices = [];
        $correctIndex = $data['correct_choice'] ?? ($data['correct_choice_id'] ?? null);

        if (isset($data['choices']) && is_array($data['choices'])) {
            foreach ($data['choices'] as $idx => $c) {
                $text = is_array($c) ? ($c['content'] ?? ($c['choice_text'] ?? '')) : (string) $c;
                if (!empty(trim($text))) {
                    $choices[] = trim($text);
                }
            }
        } elseif ($question) {
            $dbChoices = $question->choices()->get();
            foreach ($dbChoices as $idx => $dbc) {
                $text = (string) ($dbc->content ?? $dbc->choice_text ?? '');
                if (!empty(trim($text))) {
                    $choices[] = trim($text);
                }
                if ($dbc->is_correct) {
                    $correctIndex = $idx;
                }
            }
        }

        return [
            'choices'       => $choices,
            'correct_index' => $correctIndex,
        ];
    }

    /**
     * Helper to extract media info from array or model.
     */
    protected static function extractMedia(array $data, ?Question $question = null): array
    {
        $hasImage = !empty($data['image_url']);
        $hasAudio = !empty($data['audio_url']);
        $passageId = $data['passage_id'] ?? ($question?->passage_id ?? null);
        $passageText = trim((string) ($data['passage_text'] ?? ($question?->passage_text ?? '')));
        $hasPassageGroup = !empty($data['passage_group_id']) || !empty($question?->passage_group_id);

        $mediaAssetId = $data['media_asset_id'] ?? ($question?->media_asset_id ?? null);
        if (!empty($mediaAssetId)) {
            $asset = MediaAsset::find($mediaAssetId);
            if ($asset) {
                if ($asset->type === 'image') $hasImage = true;
                if ($asset->type === 'audio') $hasAudio = true;
                if ($asset->type === 'passage') {
                    $passageText = $passageText ?: ($asset->content_text ?? '');
                }
            }
        }

        if ($question) {
            if (!$hasImage && !empty($question->image_url)) $hasImage = true;
            if (!$hasAudio && !empty($question->audio_url)) $hasAudio = true;
            if (!$hasAudio && $question->audioGroup && (!empty($question->audioGroup->audio_url) || !empty($question->audioGroup->media_asset_id))) {
                $hasAudio = true;
            }
            if (empty($passageText) && $question->passage) {
                $passageText = $question->passage->content ?? '';
            }
        }

        return [
            'has_image'         => $hasImage,
            'has_audio'         => $hasAudio,
            'passage_id'        => $passageId,
            'passage_text'      => $passageText,
            'has_passage_group' => $hasPassageGroup,
        ];
    }
}
