<?php

namespace App\Modules\Assessment\Engines;

use App\Modules\Assessment\Models\Attempt;
use App\Modules\QuestionBank\Models\Question;
use Illuminate\Support\Collection;

class RandomizationEngine
{
    /**
     * Shuffle questions deterministically based on Attempt ID/Seed.
     *
     * @param  Collection<int, Question>  $questions
     * @return Collection<int, Question>
     */
    public function getShuffledQuestions(Attempt $attempt, Collection $questions): Collection
    {
        $shouldShuffle = $attempt->test ? $attempt->test->shuffle_questions : false;

        if (!$shouldShuffle || $questions->isEmpty()) {
            return $questions->values();
        }

        $seed = crc32(($attempt->seed ?? $attempt->id).'questions');
        $items = $questions->values()->all();

        // Deterministic Fisher-Yates Shuffle using integer seed
        mt_srand($seed);
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            $tmp = $items[$i];
            $items[$i] = $items[$j];
            $items[$j] = $tmp;
        }

        return collect($items);
    }

    /**
     * Shuffle choices deterministically based on Attempt ID & Question ID.
     *
     * @param  Collection<int, mixed>  $choices
     * @return Collection<int, mixed>
     */
    public function getShuffledChoices(Attempt $attempt, string $questionId, Collection $choices): Collection
    {
        $shouldShuffle = $attempt->test ? $attempt->test->shuffle_choices : false;

        if (!$shouldShuffle || $choices->isEmpty()) {
            return $choices->values();
        }

        $seed = crc32(($attempt->seed ?? $attempt->id).$questionId.'choices');
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
