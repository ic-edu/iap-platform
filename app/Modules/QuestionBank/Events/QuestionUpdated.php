<?php

namespace App\Modules\QuestionBank\Events;

use App\Modules\QuestionBank\Models\Question;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuestionUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Question $question
    ) {}
}
