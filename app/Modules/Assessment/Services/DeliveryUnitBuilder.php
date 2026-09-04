<?php

namespace App\Modules\Assessment\Services;

use App\Modules\Assessment\Engines\RandomizationEngine;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\AudioGroup;
use App\Modules\QuestionBank\Models\PassageGroup;
use App\Modules\QuestionBank\Models\Question;
use Illuminate\Support\Collection;

class DeliveryUnitBuilder
{
    /**
     * Build delivery units and lookup maps for an assessment test.
     *
     * @param Test $test
     * @param mixed $questionsOrAttempt
     * @param Attempt|null $attempt
     * @return array{
     *     deliveryUnits: Collection<int, array<string, mixed>>,
     *     questions: Collection<int, Question>,
     *     questionIndexToUnitIndex: array<int, int>,
     *     questionIdToUnitIndex: array<string, int>,
     *     unitIndexToQuestionIndices: array<int, array<int, int>>,
     *     sectionFirstUnitIndex: array<string, int>,
     *     unitSectionMap: array<int, string>,
     *     sections: Collection<int, TestSection>,
     *     totalQuestionsCount: int,
     *     totalUnitsCount: int
     * }
     */
    public static function build(Test $test, mixed $questionsOrAttempt = null, ?Attempt $attempt = null): array
    {
        $explicitQuestions = null;
        $activeAttempt = $attempt;

        if ($questionsOrAttempt instanceof Attempt) {
            $activeAttempt = $questionsOrAttempt;
        } elseif ($questionsOrAttempt instanceof Collection) {
            $explicitQuestions = $questionsOrAttempt;
        }

        $orderedQuestions = collect();
        $sections = $test->sections ? $test->sections->sortBy('order')->values() : collect();

        // 1. Gather Questions in Canonical Section & TestQuestion Order
        if ($explicitQuestions !== null && $explicitQuestions->isNotEmpty()) {
            $orderedQuestions = $explicitQuestions->values();
        } else {
            foreach ($sections as $section) {
                $secQuestions = $section->testQuestions ? $section->testQuestions->sortBy('order')->map(function ($tq) use ($section) {
                    $q = $tq->question;
                    if ($q) {
                        $q->section_model = $section;
                        $q->test_question_order = $tq->order;
                    }
                    return $q;
                })->filter()->values() : collect();

                if ($activeAttempt && (new RandomizationEngine)->isQuestionShuffleAllowed($activeAttempt)) {
                    $secQuestions = (new RandomizationEngine)->getShuffledQuestions($activeAttempt, $secQuestions);
                }

                foreach ($secQuestions as $q) {
                    $orderedQuestions->push($q);
                }
            }
        }

        // 2. Group Questions into Atomic Delivery Units
        $rawUnits = [];
        $globalQIndex = 0;
        $currentUnit = null;

        foreach ($orderedQuestions as $q) {
            $section = $q->section_model ?? ($sections->firstWhere('id', $q->testQuestions->first()?->test_section_id) ?? $sections->first());
            $sectionId = $section?->id ?? 'default';
            $partNum = (int) ($q->part_number ?? 0);
            $audioGroupId = $q->audio_group_id;
            $passageGroupId = $q->passage_group_id ?? ($q->passage_id ? 'p_' . $q->passage_id : null);

            $isAudioGroup = !empty($audioGroupId) || $q->audioGroup !== null;
            $isPassageGroup = !empty($passageGroupId) || $q->passageGroup !== null || $q->passage !== null || (!empty($partNum) && in_array($partNum, [6, 7], true) && $q->getEffectivePassages()->isNotEmpty());

            // Check if current open unit can absorb this question
            if (
                $currentUnit !== null
                && $currentUnit['section_id'] === $sectionId
                && $currentUnit['type'] === 'audio_group'
                && $isAudioGroup
                && $currentUnit['audio_group_id'] === $audioGroupId
            ) {
                // Append to existing AudioGroup Delivery Unit
                $currentUnit['questions']->push($q);
                $currentUnit['question_indices'][] = $globalQIndex;
            } elseif (
                $currentUnit !== null
                && $currentUnit['section_id'] === $sectionId
                && $currentUnit['type'] === 'passage_group'
                && $isPassageGroup
                && $currentUnit['passage_group_id'] === ($passageGroupId ?: 'pg_' . $sectionId)
            ) {
                // Append to existing PassageGroup Delivery Unit
                $currentUnit['questions']->push($q);
                $currentUnit['question_indices'][] = $globalQIndex;
            } else {
                // Finalize previous open unit
                if ($currentUnit !== null) {
                    $rawUnits[] = $currentUnit;
                    $currentUnit = null;
                }

                $unitIdx = count($rawUnits);

                if ($isAudioGroup) {
                    $audioGroup = $q->audioGroup;
                    $currentUnit = [
                        'index'            => $unitIdx,
                        'type'             => 'audio_group',
                        'audio_group_id'   => $audioGroupId,
                        'audio_group'      => $audioGroup,
                        'passage_group_id' => null,
                        'passage_group'    => null,
                        'passages'         => collect(),
                        'part_number'      => $partNum,
                        'section_id'       => $sectionId,
                        'section'          => $section,
                        'questions'        => collect([$q]),
                        'question_indices' => [$globalQIndex],
                        'title'            => $audioGroup?->title,
                        'audio_url'        => $audioGroup?->getEffectiveAudioUrl() ?: $q->getEffectiveAudioUrl(),
                        'media_asset'      => $audioGroup?->mediaAsset ?: $q->mediaAsset,
                        'group_type'       => $audioGroup?->group_type ?: ($partNum === 3 ? 'conversation' : 'talk'),
                    ];
                } elseif ($isPassageGroup) {
                    $passageGroup = $q->passageGroup;
                    $passages = $passageGroup ? $passageGroup->passages : $q->getEffectivePassages();
                    $effectivePGroupId = $passageGroupId ?: ($passageGroup?->id ?: 'pg_' . $sectionId . '_' . $unitIdx);

                    $currentUnit = [
                        'index'            => $unitIdx,
                        'type'             => 'passage_group',
                        'audio_group_id'   => null,
                        'audio_group'      => null,
                        'passage_group_id' => $effectivePGroupId,
                        'passage_group'    => $passageGroup,
                        'passages'         => $passages,
                        'part_number'      => $partNum,
                        'section_id'       => $sectionId,
                        'section'          => $section,
                        'questions'        => collect([$q]),
                        'question_indices' => [$globalQIndex],
                        'title'            => $passageGroup?->title,
                        'passage_type'     => $passageGroup?->passage_type ?: ($partNum === 6 ? 'text' : 'single'),
                        'audio_url'        => null,
                        'media_asset'      => null,
                        'group_type'       => null,
                    ];
                } else {
                    $rawUnits[] = [
                        'index'            => $unitIdx++,
                        'type'             => 'question',
                        'audio_group_id'   => null,
                        'audio_group'      => null,
                        'passage_group_id' => null,
                        'passage_group'    => null,
                        'passages'         => $q->getEffectivePassages(),
                        'part_number'      => $partNum,
                        'section_id'       => $sectionId,
                        'section'          => $section,
                        'questions'        => collect([$q]),
                        'question_indices' => [$globalQIndex],
                        'title'            => null,
                        'audio_url'        => $q->getEffectiveAudioUrl(),
                        'image_url'        => $q->getEffectiveImageUrl(),
                        'media_asset'      => $q->mediaAsset,
                        'group_type'       => null,
                    ];
                    $currentUnit = null;
                }
            }

            $globalQIndex++;
        }

        if ($currentUnit !== null) {
            $rawUnits[] = $currentUnit;
            $currentUnit = null;
        }

        // 3. Post-process Units, Assign Canonical AGN and Boundary State
        $finalUnits = collect();
        $sectionFirstUnitIndex = [];
        $questionIndexToUnitIndex = [];
        $questionIdToUnitIndex = [];
        $unitSectionMap = [];
        $unitIndexToQuestionIndices = [];

        foreach ($rawUnits as $uIdx => $unit) {
            $unit['index'] = $uIdx;
            $unitQuestions = $unit['questions'];
            $qIndices = $unit['question_indices'];
            $firstQ = min($qIndices);
            $lastQ = max($qIndices);
            $secId = $unit['section_id'];

            if (!isset($sectionFirstUnitIndex[$secId])) {
                $sectionFirstUnitIndex[$secId] = $uIdx;
            }

            // Assign canonical Actual Global Numbering (AGN: Q1..QN) to each question
            $canonicalNumbers = [];
            foreach ($unitQuestions as $cIdx => $uq) {
                $qIdx = $qIndices[$cIdx];
                $agn = $qIdx + 1;
                $uq->canonical_global_number = $agn;
                $uq->agn = $agn;
                $canonicalNumbers[] = $agn;

                $questionIndexToUnitIndex[$qIdx] = $uIdx;
                $questionIdToUnitIndex[$uq->id] = $uIdx;
            }

            $unit['canonical_question_numbers'] = $canonicalNumbers;
            $unitSectionMap[$uIdx] = $secId;
            $unitIndexToQuestionIndices[$uIdx] = $qIndices;

            $isFirstUnitOfSection = ($sectionFirstUnitIndex[$secId] === $uIdx);

            // Check if next unit belongs to a different section
            $nextUnit = $rawUnits[$uIdx + 1] ?? null;
            $isLastUnitOfSection = ($nextUnit === null) || ($nextUnit['section_id'] !== $secId);
            $nextSectionId = ($isLastUnitOfSection && $nextUnit) ? $nextUnit['section_id'] : null;

            // Check previous section
            $prevUnit = $rawUnits[$uIdx - 1] ?? null;
            $prevSectionId = ($prevUnit && $prevUnit['section_id'] !== $secId) ? $prevUnit['section_id'] : null;

            $displayRange = (count($qIndices) > 1)
                ? 'Questions ' . ($firstQ + 1) . '–' . ($lastQ + 1)
                : 'Question ' . ($firstQ + 1);

            $unit['first_question_index']     = $firstQ;
            $unit['last_question_index']      = $lastQ;
            $unit['display_question_range']   = $displayRange;
            $unit['is_first_unit_of_section'] = $isFirstUnitOfSection;
            $unit['is_last_unit_of_section']  = $isLastUnitOfSection;
            $unit['next_section_id']          = $nextSectionId;
            $unit['prev_section_id']          = $prevSectionId;

            $finalUnits->push($unit);
        }

        return [
            'deliveryUnits'              => $finalUnits,
            'questions'                  => $orderedQuestions,
            'questionIndexToUnitIndex'   => $questionIndexToUnitIndex,
            'questionIdToUnitIndex'      => $questionIdToUnitIndex,
            'unitIndexToQuestionIndices' => $unitIndexToQuestionIndices,
            'sectionFirstUnitIndex'      => $sectionFirstUnitIndex,
            'unitSectionMap'             => $unitSectionMap,
            'sections'                   => $sections,
            'totalQuestionsCount'        => $orderedQuestions->count(),
            'totalUnitsCount'            => $finalUnits->count(),
        ];
    }
}
