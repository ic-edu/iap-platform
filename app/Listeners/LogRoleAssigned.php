<?php

namespace App\Listeners;

use App\Events\RoleAssigned;
use App\Services\ActivityLogger;

class LogRoleAssigned
{
    public function handle(RoleAssigned $event): void
    {
        ActivityLogger::log(
            action: 'role.assigned',
            description: "Role '{$event->roleName}' assigned to user {$event->user->email}.",
            subject: $event->user,
            properties: ['role' => $event->roleName]
        );
    }
}
