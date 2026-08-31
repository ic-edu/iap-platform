<?php

namespace App\Modules\Assessment\Services;

use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\AudioGroup;
use App\Modules\QuestionBank\Models\Question;
use Illuminate\Support\Collection;

class DeliveryUnitBuilder
{
    /**
     * Build delivery units and lookup maps for an assessment test.
     *
     * @param Test $test
     * @param Collection<int, Question>|null $questions
     * @return array{
     *     deliveryUnits: Collection<int, array<string, mixed>>,
     *     questions: Collection<int, Question>,
     *     questionIndexToUnitIndex: array<int, int>,
     *     questionIdToUnitIndex: array<string, int>,
     *     unitIndexToQuestionIndices: array<int, array<int, int>>,
     *     sectionFirstUnitIndex: array<string, int>,
     *     unitSectionMap: array<int, string>,
     *     totalQuestionsCount: int,
     *     totalUnitsCount: int
     * }
     */
    public static function build(Test $test, ?Collection $questions = null): array
    {
        $orderedQuestions = collect();
        $rawUnits = [];
        $sections = $test->sections ? $test->sections->sortBy('order')->values() : collect();

        // If a pre-ordered or pre-shuffled list of questions is passed
        if ($questions !== null && $questions->isNotEmpty()) {
            $orderedQuestions = $questions->values();
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

                foreach ($secQuestions as $q) {
                    $orderedQuestions->push($q);
                }
            }
        }

        // Group into delivery units section by section
        $globalQIndex = 0;
        $currentUnit = null;

        foreach ($orderedQuestions as $q) {
            $section = $q->section_model ?? ($sections->firstWhere('id', $q->testQuestions->first()?->test_section_id) ?? $sections->first());
            $sectionId = $section?->id ?? 'default';
            $partNum = (int) ($q->part_number ?? 0);
            $audioGroupId = $q->audio_group_id;
            $isAudioGroup = in_array($partNum, [3, 4], true) && !empty($audioGroupId);

            // Check if current unit can absorb this question
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
            } else {
                // Finalize previous unit if open
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
                } else {
                    $rawUnits[] = [
                        'index'            => $unitIdx,
                        'type'             => 'question',
                        'audio_group_id'   => null,
                        'audio_group'      => null,
                        'part_number'      => $partNum,
                        'section_id'       => $sectionId,
                        'section'          => $section,
                        'questions'        => collect([$q]),
                        'question_indices' => [$globalQIndex],
                        'title'            => null,
                        'audio_url'        => $q->getEffectiveAudioUrl(),
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

        // Post-process units to add bounds, ranges, and section navigation transitions
        $totalUnits = count($rawUnits);
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

            // Map question indices and question IDs to unit
            foreach ($qIndices as $qi) {
                $questionIndexToUnitIndex[$qi] = $uIdx;
            }
            foreach ($unitQuestions as $uq) {
                $questionIdToUnitIndex[$uq->id] = $uIdx;
            }
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
            'totalQuestionsCount'        => $orderedQuestions->count(),
            'totalUnitsCount'            => $finalUnits->count(),
        ];
    }
}
