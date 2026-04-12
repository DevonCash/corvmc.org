<?php

namespace CorvMC\Finance\Actions\Subscriptions;

use App\Models\User;
use Brick\Money\Money;
use CorvMC\Finance\Services\SubscriptionService;
use Laravel\Cashier\Checkout;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use SubscriptionService::updateSubscriptionAmount() instead
 * This action is maintained for backward compatibility only.
 * New code should use the SubscriptionService directly.
 */
class UpdateSubscriptionAmount
{
    use AsAction;

    /**
     * @deprecated Use SubscriptionService::updateSubscriptionAmount() instead
     */
    public function handle(User $user, Money $baseAmount, bool $coverFees = false): ?Checkout
    {
        return app(SubscriptionService::class)->updateSubscriptionAmount($user, $baseAmount, $coverFees);
    }
}
