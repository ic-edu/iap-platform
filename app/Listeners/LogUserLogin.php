<?php

namespace App\Listeners;

use App\Events\UserLoggedIn;
use App\Services\ActivityLogger;

class LogUserLogin
{
    public function handle(UserLoggedIn $event): void
    {
        ActivityLogger::log(
            action: 'auth.login',
            description: "User {$event->user->email} logged in.",
            subject: $event->user,
            userId: $event->user->id
        );
    }
}
