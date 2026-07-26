<?php

namespace App\Modules\Commerce\Events;

use App\Modules\Commerce\Domain\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionActivated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Subscription $subscription) {}
}
