<?php

namespace App\Listeners;

use App\Events\ApiTokenCreated;
use App\Events\ApiTokenRevoked;
use App\Events\WebhookDelivered;
use App\Events\WebhookFailed;
use App\Services\ActivityLogger;

class LogApiPlatformActivity
{
    public function __construct(protected ActivityLogger $logger) {}

    public function handleApiTokenCreated(ApiTokenCreated $event): void
    {
        $this->logger->log(
            'api_token_created',
            "API Token {$event->tokenName} created for user {$event->user->email}",
            null,
            null,
            $event->user->id
        );
    }

    public function handleApiTokenRevoked(ApiTokenRevoked $event): void
    {
        $this->logger->log(
            'api_token_revoked',
            "API Token revoked for user {$event->user->email}",
            null,
            null,
            $event->user->id
        );
    }

    public function handleWebhookDelivered(WebhookDelivered $event): void
    {
        $this->logger->log(
            'webhook_delivered',
            "Webhook event {$event->delivery->event} delivered to target {$event->delivery->subscription?->target_url}",
            $event->delivery
        );
    }

    public function handleWebhookFailed(WebhookFailed $event): void
    {
        $this->logger->log(
            'webhook_failed',
            "Webhook event {$event->delivery->event} delivery failed for target {$event->delivery->subscription?->target_url}",
            $event->delivery
        );
    }
}
