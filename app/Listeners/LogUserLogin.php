<?php

namespace App\Listeners;

use App\Events\UserLoggedIn;
use App\Services\ActivityLogger;

class LogUserLogin
{
    public function handle(UserLoggedIn $event): void
    {
        ActivityLogger::log(
            action: 'LOGIN',
            description: "User {$event->user->name} ({$event->user->email}) logged in successfully.",
            subject: $event->user,
            userId: $event->user->id
        );
    }
}
