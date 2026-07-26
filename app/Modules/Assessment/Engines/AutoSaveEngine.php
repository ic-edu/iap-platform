<?php

namespace App\Modules\Assessment\Engines;

use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;

class AutoSaveEngine
{
    /**
     * Auto save or update candidate response for a question.
     *
     * @param  array<string, mixed>|string|null  $selectedChoices
     */
    public function saveAnswer(
        Attempt $attempt,
        string $questionId,
        mixed $selectedChoices = null,
        ?string $answerText = null
    ): Answer {
        $selectedChoiceId = is_string($selectedChoices) ? $selectedChoices : null;

        $answer = Answer::updateOrCreate(
            [
                'attempt_id' => $attempt->id,
                'question_id' => $questionId,
            ],
            [
                'selected_choice_id' => $selectedChoiceId,
                'text_response' => $answerText ?? (is_array($selectedChoices) ? json_encode($selectedChoices) : null),
            ]
        );

        return $answer;
    }
}
