<?php

namespace App\Modules\Assessment\Engines;

use App\Modules\Assessment\Models\Attempt;

class NavigationEngine
{
    /**
     * Toggle flag state for a question in an attempt.
     */
    public function toggleFlag(Attempt $attempt, string $questionId): Attempt
    {
        $flagged = $attempt->flagged_questions ?? [];

        if (in_array($questionId, $flagged, true)) {
            $flagged = array_values(array_diff($flagged, [$questionId]));
        } else {
            $flagged[] = $questionId;
        }

        $attempt->update(['flagged_questions' => $flagged]);

        return $attempt;
    }

    /**
     * Toggle mark for review state for a question in an attempt.
     */
    public function toggleReviewLater(Attempt $attempt, string $questionId): Attempt
    {
        $reviewLater = $attempt->review_later_questions ?? [];

        if (in_array($questionId, $reviewLater, true)) {
            $reviewLater = array_values(array_diff($reviewLater, [$questionId]));
        } else {
            $reviewLater[] = $questionId;
        }

        $attempt->update(['review_later_questions' => $reviewLater]);

        return $attempt;
    }

    /**
     * Set current active question ID.
     */
    public function setCurrentQuestion(Attempt $attempt, string $questionId): Attempt
    {
        $attempt->update(['current_question_id' => $questionId]);

        return $attempt;
    }
}
