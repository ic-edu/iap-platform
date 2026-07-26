<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SdkGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $language) {}
}
