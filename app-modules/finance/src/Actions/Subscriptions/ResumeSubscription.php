<?php

namespace CorvMC\Finance\Actions\Subscriptions;

use App\Models\User;
use CorvMC\Finance\Services\SubscriptionService;

/**
 * @deprecated Use SubscriptionService::resumeSubscription() instead
 * This action is maintained for backward compatibility only.
 * New code should use the SubscriptionService directly.
 */
class ResumeSubscription
{
    /**
     * @deprecated Use SubscriptionService::resumeSubscription() instead
     */
    public function handle(User $user): void
    {
        app(SubscriptionService::class)->resumeSubscription($user);
    }
}
