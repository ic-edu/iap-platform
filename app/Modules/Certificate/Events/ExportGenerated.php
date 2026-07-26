<?php

namespace App\Modules\Certificate\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExportGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $type,
        public int $recordCount
    ) {}
}
