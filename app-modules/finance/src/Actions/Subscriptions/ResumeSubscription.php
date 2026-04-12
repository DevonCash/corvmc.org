<?php

namespace CorvMC\Finance\Actions\Subscriptions;

use App\Models\User;
use CorvMC\Finance\Services\SubscriptionService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use SubscriptionService::resumeSubscription() instead
 * This action is maintained for backward compatibility only.
 * New code should use the SubscriptionService directly.
 */
class ResumeSubscription
{
    use AsAction;

    /**
     * @deprecated Use SubscriptionService::resumeSubscription() instead
     */
    public function handle(User $user): void
    {
        app(SubscriptionService::class)->resumeSubscription($user);
    }
}
