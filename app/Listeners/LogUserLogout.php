<?php

namespace App\Listeners;

use App\Events\UserLoggedOut;
use App\Services\ActivityLogger;

class LogUserLogout
{
    public function handle(UserLoggedOut $event): void
    {
        ActivityLogger::log(
            action: 'LOGOUT',
            description: "User {$event->user->name} ({$event->user->email}) logged out.",
            subject: $event->user,
            userId: $event->user->id
        );
    }
}
