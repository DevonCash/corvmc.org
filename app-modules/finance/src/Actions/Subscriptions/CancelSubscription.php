<?php

namespace CorvMC\Finance\Actions\Subscriptions;

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Finance\Services\SubscriptionService;

/**
 * @deprecated Use SubscriptionService::cancelSubscription() instead
 * This action is maintained for backward compatibility only.
 * New code should use the SubscriptionService directly.
 */
class CancelSubscription
{
    /**
     * @deprecated Use SubscriptionService::cancelSubscription() instead
     */
    public function handle(User $user): Carbon
    {
        return app(SubscriptionService::class)->cancelSubscription($user);
    }
}
