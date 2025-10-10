<?php

namespace App\Actions\Subscriptions;

use App\Models\User;
use Laravel\Cashier\Subscription;
use Lorisleiva\Actions\Concerns\AsAction;

class GetActiveSubscription
{
    use AsAction;

    /**
     * Get user's active membership subscription.
     */
    public function handle(User $user): ?Subscription
    {
        $subscription = $user->subscription('default');

        return $subscription && $subscription->active() ? $subscription : null;
    }
}
