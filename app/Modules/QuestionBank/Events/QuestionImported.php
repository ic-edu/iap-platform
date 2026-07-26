<?php

namespace App\Modules\QuestionBank\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuestionImported
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $bankId,
        public int $count
    ) {}
}
