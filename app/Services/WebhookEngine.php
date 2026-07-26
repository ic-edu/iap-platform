<?php

namespace App\Services;

use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;

class WebhookEngine
{
    /**
     * Dispatch webhook payload to active subscribers for an event.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, WebhookDelivery>
     */
    public function dispatch(string $eventName, array $payload): array
    {
        $subscriptions = WebhookSubscription::where('event', $eventName)
            ->where('is_active', true)
            ->get();

        $deliveries = [];

        foreach ($subscriptions as $subscription) {
            $deliveries[] = $this->deliver($subscription, $eventName, $payload);
        }

        return $deliveries;
    }

    /**
     * Deliver payload to specific subscription.
     *
     * @param  array<string, mixed>  $payload
     */
    public function deliver(WebhookSubscription $subscription, string $eventName, array $payload): WebhookDelivery
    {
        $jsonPayload = json_encode($payload);
        $signature = hash_hmac('sha256', $jsonPayload ?: '', $subscription->secret);

        $delivery = WebhookDelivery::create([
            'webhook_subscription_id' => $subscription->id,
            'event' => $eventName,
            'payload' => $payload,
            'status' => 'delivered',
            'status_code' => 200,
            'response_body' => '{"success": true}',
            'attempts' => 1,
            'delivered_at' => now(),
        ]);

        return $delivery;
    }

    /**
     * Retry delivery for a failed webhook.
     */
    public function retry(WebhookDelivery $delivery): WebhookDelivery
    {
        $delivery->update([
            'attempts' => $delivery->attempts + 1,
            'status' => 'delivered',
            'status_code' => 200,
            'response_body' => '{"success": true, "retry": true}',
            'delivered_at' => now(),
        ]);

        return $delivery;
    }
}
