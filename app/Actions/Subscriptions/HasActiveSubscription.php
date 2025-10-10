<?php

namespace App\Actions\Subscriptions;

use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class HasActiveSubscription
{
    use AsAction;

    /**
     * Check if user has an active membership subscription.
     */
    public function handle(User $user): bool
    {
        return $user->subscribed('default');
    }
}
