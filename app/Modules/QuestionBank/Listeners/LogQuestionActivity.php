<?php

namespace App\Modules\QuestionBank\Listeners;

use App\Modules\QuestionBank\Events\QuestionCreated;
use App\Modules\QuestionBank\Events\QuestionDeleted;
use App\Modules\QuestionBank\Events\QuestionUpdated;
use App\Services\ActivityLogger;

class LogQuestionActivity
{
    public function handleQuestionCreated(QuestionCreated $event): void
    {
        ActivityLogger::log(
            action: 'question_bank.question_created',
            description: "Question '{$event->question->prompt}' was created.",
            subject: $event->question
        );
    }

    public function handleQuestionUpdated(QuestionUpdated $event): void
    {
        ActivityLogger::log(
            action: 'question_bank.question_updated',
            description: "Question '{$event->question->prompt}' was updated.",
            subject: $event->question
        );
    }

    public function handleQuestionDeleted(QuestionDeleted $event): void
    {
        ActivityLogger::log(
            action: 'question_bank.question_deleted',
            description: "Question '{$event->question->prompt}' was deleted.",
            subject: $event->question
        );
    }
}
