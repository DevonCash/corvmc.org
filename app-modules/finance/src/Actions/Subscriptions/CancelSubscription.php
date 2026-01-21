<?php

namespace CorvMC\Finance\Actions\Subscriptions;

use App\Exceptions\SubscriptionNotFoundException;
use CorvMC\Membership\Models\User;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class CancelSubscription
{
    use AsAction;

    /**
     * Cancel a user's subscription at period end.
     *
     * @throws SubscriptionNotFoundException
     */
    public function handle(User $user): Carbon
    {
        $subscription = $user->subscription('default');

        if (! $subscription || ! $subscription->active()) {
            throw new SubscriptionNotFoundException('No active membership subscription found');
        }

        $subscription->cancel();

        return $subscription->ends_at;
    }
}
