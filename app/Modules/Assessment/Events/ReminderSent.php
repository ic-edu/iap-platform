<?php

namespace App\Modules\Assessment\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReminderSent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public string $reminderType
    ) {}
}
