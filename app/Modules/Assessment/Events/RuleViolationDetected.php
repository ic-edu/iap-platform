<?php

namespace App\Modules\Assessment\Events;

use App\Modules\Assessment\Models\Attempt;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RuleViolationDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Attempt $attempt,
        public string $violationType
    ) {}
}
