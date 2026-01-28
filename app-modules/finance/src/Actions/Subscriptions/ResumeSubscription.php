<?php

namespace CorvMC\Finance\Actions\Subscriptions;

use CorvMC\Finance\Exceptions\SubscriptionNotFoundException;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class ResumeSubscription
{
    use AsAction;

    /**
     * Resume a cancelled subscription.
     *
     * @throws SubscriptionNotFoundException
     */
    public function handle(User $user): void
    {
        $subscription = $user->subscription('default');

        if (! $subscription || ! $subscription->canceled()) {
            throw new SubscriptionNotFoundException('No cancelled subscription found to resume');
        }

        $subscription->resume();
    }
}
