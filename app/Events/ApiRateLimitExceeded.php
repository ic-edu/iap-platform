<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApiRateLimitExceeded
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $ip, public ?int $userId = null) {}
}
