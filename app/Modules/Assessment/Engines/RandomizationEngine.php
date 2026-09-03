<?php

namespace App\Modules\Assessment\Engines;

use App\Modules\Assessment\Models\Attempt;
use App\Modules\QuestionBank\Models\Question;
use App\Services\ToeicQuestionValidator;
use Illuminate\Support\Collection;

class RandomizationEngine
{
    /**
     * Determine if structural question randomization is allowed for this attempt / test.
     */
    public function isQuestionShuffleAllowed(Attempt $attempt): bool
    {
        $test = $attempt->test;
        if (!$test || !$test->shuffle_questions) {
            return false;
        }

        // Standardized assessments (TOEIC, TOEFL, IELTS, etc.) must NEVER shuffle section or question structure
        if (method_exists($test, 'isStandardizedTest') && $test->isStandardizedTest()) {
            return false;
        }

        if (ToeicQuestionValidator::isToeic($test)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if choice shuffling is allowed for this question / attempt.
     */
    public function isChoiceShuffleAllowed(Attempt $attempt, string|Question|null $question = null): bool
    {
        $test = $attempt->test;
        if (!$test || !$test->shuffle_choices) {
            return false;
        }

        $qObj = null;
        if ($question instanceof Question) {
            $qObj = $question;
        } elseif (is_string($question)) {
            $qObj = Question::find($question);
        }

        // For TOEIC Part 1 (A/B/C/D) and Part 2 (A/B/C), choices correspond to spoken audio statements in fixed order
        if ($qObj && ToeicQuestionValidator::isToeic($qObj)) {
            $part = (int) ($qObj->part_number ?? 0);
            if (in_array($part, [1, 2], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Shuffle questions deterministically based on Attempt ID/Seed while preserving section & group boundaries.
     *
     * @param  Collection<int, Question>  $questions
     * @return Collection<int, Question>
     */
    public function getShuffledQuestions(Attempt $attempt, Collection $questions): Collection
    {
        if (!$this->isQuestionShuffleAllowed($attempt) || $questions->isEmpty()) {
            return $questions->values();
        }

        // Group questions by section so shuffle NEVER crosses section boundaries
        $groupedBySection = $questions->groupBy(function (Question $q) {
            return $q->section_model?->id ?? ($q->testQuestions->first()?->test_section_id ?? 'default');
        });

        $result = collect();
        $baseSeed = crc32(($attempt->seed ?? $attempt->id) . '_questions_v2');

        foreach ($groupedBySection as $secId => $secQuestions) {
            // Group contiguous audio_group or passage_group questions into atomic clusters
            $clusters = [];
            $currentCluster = null;

            foreach ($secQuestions as $q) {
                $audioGrpId = $q->audio_group_id;
                $passGrpId = $q->passage_group_id;

                if ($audioGrpId && $currentCluster && $currentCluster['type'] === 'audio' && $currentCluster['id'] === $audioGrpId) {
                    $currentCluster['items'][] = $q;
                } elseif ($passGrpId && $currentCluster && $currentCluster['type'] === 'passage' && $currentCluster['id'] === $passGrpId) {
                    $currentCluster['items'][] = $q;
                } else {
                    if ($currentCluster !== null) {
                        $clusters[] = $currentCluster;
                    }
                    if ($audioGrpId) {
                        $currentCluster = ['type' => 'audio', 'id' => $audioGrpId, 'items' => [$q]];
                    } elseif ($passGrpId) {
                        $currentCluster = ['type' => 'passage', 'id' => $passGrpId, 'items' => [$q]];
                    } else {
                        $clusters[] = ['type' => 'standalone', 'id' => $q->id, 'items' => [$q]];
                        $currentCluster = null;
                    }
                }
            }

            if ($currentCluster !== null) {
                $clusters[] = $currentCluster;
            }

            // Shuffle clusters within section deterministically
            $secSeed = crc32($baseSeed . '_' . $secId);
            mt_srand($secSeed);
            for ($i = count($clusters) - 1; $i > 0; $i--) {
                $j = mt_rand(0, $i);
                $tmp = $clusters[$i];
                $clusters[$i] = $clusters[$j];
                $clusters[$j] = $tmp;
            }

            foreach ($clusters as $cluster) {
                foreach ($cluster['items'] as $item) {
                    $result->push($item);
                }
            }
        }

        return $result;
    }

    /**
     * Shuffle choices deterministically based on Attempt ID & Question ID.
     *
     * @param  Collection<int, mixed>  $choices
     * @return Collection<int, mixed>
     */
    public function getShuffledChoices(Attempt $attempt, string|Question $question, Collection $choices): Collection
    {
        $questionId = $question instanceof Question ? $question->id : (string) $question;

        if (!$this->isChoiceShuffleAllowed($attempt, $question) || $choices->isEmpty()) {
            return $choices->values();
        }

        $seed = crc32(($attempt->seed ?? $attempt->id) . $questionId . 'choices');
        $items = $choices->values()->all();

        mt_srand($seed);
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            $tmp = $items[$i];
            $items[$i] = $items[$j];
            $items[$j] = $tmp;
        }

        return collect($items);
    }
}
