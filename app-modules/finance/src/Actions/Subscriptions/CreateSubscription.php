<?php

namespace CorvMC\Finance\Actions\Subscriptions;

use App\Models\User;
use Brick\Money\Money;
use CorvMC\Finance\Services\SubscriptionService;
use Laravel\Cashier\Checkout;

/**
 * @deprecated Use SubscriptionService::createSubscription() instead
 * This action is maintained for backward compatibility only.
 * New code should use the SubscriptionService directly.
 */
class CreateSubscription
{
    /**
     * @deprecated Use SubscriptionService::createSubscription() instead
     */
    public function handle(User $user, Money $baseAmount, bool $coverFees = false): Checkout
    {
        return app(SubscriptionService::class)->createSubscription($user, $baseAmount, $coverFees);
    }

    // All helper methods removed - functionality moved to SubscriptionService
}
