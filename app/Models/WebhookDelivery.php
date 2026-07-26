<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $webhook_subscription_id
 * @property string $event
 * @property array<string, mixed> $payload
 * @property int|null $status_code
 * @property string|null $response_body
 * @property string $status
 * @property int $attempts
 * @property Carbon|null $delivered_at
 * @property WebhookSubscription|null $subscription
 */
class WebhookDelivery extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'webhook_deliveries';

    protected $fillable = [
        'webhook_subscription_id',
        'event',
        'payload',
        'status_code',
        'response_body',
        'status',
        'attempts',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * Get parent subscription.
     *
     * @return BelongsTo<WebhookSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'webhook_subscription_id');
    }
}
