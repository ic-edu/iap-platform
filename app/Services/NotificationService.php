<?php

namespace App\Services;

use App\Models\User;

class NotificationService
{
    /**
     * Send notification to user.
     */
    public function send(User $user, string $title, string $message, string $category = 'system'): void
    {
        // Database / Queue Notification dispatch
    }
}
