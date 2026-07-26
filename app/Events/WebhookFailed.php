<?php

namespace App\Events;

use App\Models\WebhookDelivery;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebhookFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(public WebhookDelivery $delivery) {}
}
