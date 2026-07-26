<?php

namespace App\Modules\Academic\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImportCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $type,
        public int $importedCount
    ) {}
}
