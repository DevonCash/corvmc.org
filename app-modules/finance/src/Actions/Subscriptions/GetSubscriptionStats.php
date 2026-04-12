<?php

namespace CorvMC\Finance\Actions\Subscriptions;

use CorvMC\Finance\Data\SubscriptionStatsData;
use CorvMC\Finance\Services\SubscriptionService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use SubscriptionService::getSubscriptionStats() instead
 * This action is maintained for backward compatibility only.
 * New code should use the SubscriptionService directly.
 */
class GetSubscriptionStats
{
    use AsAction;

    /**
     * @deprecated Use SubscriptionService::getSubscriptionStats() instead
     */
    public function handle(): SubscriptionStatsData
    {
        return app(SubscriptionService::class)->getSubscriptionStats();
    }
}