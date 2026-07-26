<?php

namespace App\Modules\Academic\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BatchOperationCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $operation,
        public int $affectedCount
    ) {}
}
