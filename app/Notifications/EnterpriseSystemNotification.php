<?php

namespace App\Notifications;

use App\Services\ActivityLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EnterpriseSystemNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public string $type = 'SYSTEM_ALERT',
        public string $priority = 'HIGH',
        public ?string $entityType = null,
        public ?string $entityId = null,
        public ?string $targetUrl = null
    ) {}

    /**
     * Get notification channels.
     *
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get representation array for database notification schema (ADMIN-OPS-001 Section 7 & 8).
     *
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $targetUrl = $this->targetUrl ?? route('notifications.index');

        // Audit Log NOTIFICATION_CREATED (ADMIN-OPS-001 Section 15)
        try {
            ActivityLogger::log(
                action: 'NOTIFICATION_CREATED',
                description: "Created notification '{$this->title}' for user #{$notifiable->id}",
                subject: $notifiable,
                properties: [
                    'type' => $this->type,
                    'priority' => $this->priority,
                    'entity_type' => $this->entityType,
                    'entity_id' => $this->entityId,
                    'target_url' => $targetUrl,
                ]
            );
        } catch (\Throwable $e) {
            // Silently handle in dev
        }

        return [
            'title' => $this->title,
            'message' => $this->message,
            'notification_type' => $this->type,
            'priority' => $this->priority,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'target_url' => $targetUrl,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
