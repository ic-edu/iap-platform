<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserWelcomeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $userName
    ) {}

    /**
     * Get notification channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get mail representation.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to iC.edu Assessment Platform')
            ->greeting('Hello '.$this->userName.'!')
            ->line('Welcome to iC.edu Assessment Platform (IAP). Your account is now active.')
            ->action('Access Dashboard', url('/dashboard'));
    }

    /**
     * Get array representation for database notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Welcome to IAP',
            'message' => "Welcome {$this->userName}, your account has been successfully created.",
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
