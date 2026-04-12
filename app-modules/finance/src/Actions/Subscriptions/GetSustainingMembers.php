<?php

namespace CorvMC\Finance\Actions\Subscriptions;

use CorvMC\Finance\Services\SubscriptionService;
use Illuminate\Database\Eloquent\Collection;

/**
 * @deprecated Use SubscriptionService::getSustainingMembers() instead
 * This action is maintained for backward compatibility only.
 * New code should use the SubscriptionService directly.
 */
class GetSustainingMembers
{
    /**
     * @deprecated Use SubscriptionService::getSustainingMembers() instead
     */
    public function handle(): Collection
    {
        return app(SubscriptionService::class)->getSustainingMembers();
    }
}
