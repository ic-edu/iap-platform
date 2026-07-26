<?php

namespace App\Modules\Reporting\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SystemHealthChecked
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $healthData
     */
    public function __construct(
        public array $healthData
    ) {}
}
