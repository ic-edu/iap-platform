<?php

namespace App\Modules\Commerce\Application;

use App\Models\User;
use App\Modules\Commerce\Domain\Enums\SubscriptionStatus;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Commerce\Domain\Models\Subscription;
use App\Modules\Commerce\Events\SubscriptionActivated;
use App\Modules\Commerce\Events\SubscriptionExpired;

class SubscriptionEngine
{
    /**
     * Activate new subscription for user.
     */
    public function activateSubscription(User $user, ?Product $product = null, string $planType = 'monthly'): Subscription
    {
        $startsAt = now();
        $endsAt = match ($planType) {
            'monthly' => now()->addMonth(),
            'quarterly' => now()->addMonths(3),
            'yearly' => now()->addYear(),
            'lifetime' => null,
            default => now()->addMonth(),
        };

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'product_id' => $product?->id,
            'plan_type' => $planType,
            'status' => SubscriptionStatus::Active,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        event(new SubscriptionActivated($subscription));

        return $subscription;
    }

    /**
     * Mark subscription as expired.
     */
    public function expireSubscription(Subscription $subscription): Subscription
    {
        $subscription->update(['status' => SubscriptionStatus::Expired]);

        event(new SubscriptionExpired($subscription));

        return $subscription;
    }
}
