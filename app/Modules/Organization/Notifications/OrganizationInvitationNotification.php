<?php

namespace App\Modules\Organization\Notifications;

use App\Modules\Organization\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public OrganizationInvitation $invitation,
        public string $acceptUrl
    ) {}

    /**
     * Get the notification delivery channels.
     *
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $orgName = $this->invitation->organization?->name ?? 'Organization';
        $roleLabel = $this->invitation->intended_role->label();
        $expiresAt = $this->invitation->expires_at
            ? $this->invitation->expires_at->toFormattedDateString()
            : '7 days';

        return (new MailMessage)
            ->subject("Invitation to join {$orgName} on iC.edu Assessment Platform")
            ->greeting("Hello,")
            ->line("You have been invited to join **{$orgName}** on the **iC.edu Assessment Platform (IAP)** as an **{$roleLabel}**.")
            ->line("This invitation was issued to **{$this->invitation->email}** and will expire on **{$expiresAt}**.")
            ->action('Accept Invitation', $this->acceptUrl)
            ->line('If you did not expect this invitation or believe this was sent in error, you can safely ignore this email.')
            ->salutation("Best regards,\niC.edu Assessment Platform Team");
    }
}
