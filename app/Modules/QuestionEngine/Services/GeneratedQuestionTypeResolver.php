<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionEngine\DTO\GeneratedQuestionCandidate;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\ToeflTaskType;
use App\Modules\QuestionEngine\Models\QuestionGenerationItem;
use InvalidArgumentException;

class GeneratedQuestionTypeResolver
{
    /**
     * Resolve the canonical QuestionType for a generation item and candidate.
     *
     * @throws InvalidArgumentException
     */
    public function resolve(QuestionGenerationItem $item, ?GeneratedQuestionCandidate $candidate = null): QuestionType
    {
        $family = $item->assessment_family;

        if ($family === AssessmentFamily::Toeic) {
            return QuestionType::MultipleChoice;
        }

        if ($family === AssessmentFamily::ToeflIbt) {
            $taskTypeStr = (string) ($item->task_type ?? '');
            $taskType = ToeflTaskType::tryFrom($taskTypeStr);
            if ($taskType === null) {
                throw new InvalidArgumentException("Cannot resolve QuestionType for invalid or unknown TOEFL task type [{$taskTypeStr}].");
            }

            return match ($taskType) {
                ToeflTaskType::CompleteTheWords => ($candidate && $candidate->isMultipleChoice()) ? QuestionType::MultipleChoice : QuestionType::FillInTheBlank,
                ToeflTaskType::ReadInDailyLife,
                ToeflTaskType::ReadAnAcademicPassage,
                ToeflTaskType::ListenAndChooseAResponse,
                ToeflTaskType::ListenToAConversation,
                ToeflTaskType::ListenToAnAnnouncement,
                ToeflTaskType::ListenToAnAcademicTalk => QuestionType::MultipleChoice,
                ToeflTaskType::BuildASentence => QuestionType::Ordering,
                ToeflTaskType::WriteAnEmail => QuestionType::Writing,
                ToeflTaskType::WriteForAnAcademicDiscussion => QuestionType::Writing,
                ToeflTaskType::ListenAndRepeat,
                ToeflTaskType::TakeAnInterview => QuestionType::Speaking,
            };
        }

        if ($candidate && $candidate->isMultipleChoice()) {
            return QuestionType::MultipleChoice;
        }

        throw new InvalidArgumentException("Unable to resolve QuestionType for assessment family [{$family->value}] and task [{$item->task_type}].");
    }
}
