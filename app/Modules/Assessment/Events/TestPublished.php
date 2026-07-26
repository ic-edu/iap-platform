<?php

namespace App\Modules\Assessment\Events;

use App\Modules\Assessment\Models\Test;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TestPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Test $test
    ) {}
}
