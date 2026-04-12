<?php

namespace CorvMC\Finance\Actions\Subscriptions;

use CorvMC\Finance\Services\SubscriptionService;
use Laravel\Cashier\Subscription;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use SubscriptionService::getBillingPeriodPeakAmount() instead
 * This action is maintained for backward compatibility only.
 * New code should use the SubscriptionService directly.
 */
class GetBillingPeriodPeakAmount
{
    use AsAction;

    /**
     * @deprecated Use SubscriptionService::getBillingPeriodPeakAmount() instead
     */
    public function handle(Subscription $subscription): float
    {
        return app(SubscriptionService::class)->getBillingPeriodPeakAmount($subscription);
    }
}
