<?php

namespace CorvMC\Finance\Actions\Subscriptions;

use CorvMC\Finance\Services\SubscriptionService;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use SubscriptionService::getSustainingMembers() instead
 * This action is maintained for backward compatibility only.
 * New code should use the SubscriptionService directly.
 */
class GetSustainingMembers
{
    use AsAction;

    /**
     * @deprecated Use SubscriptionService::getSustainingMembers() instead
     */
    public function handle(): Collection
    {
        return app(SubscriptionService::class)->getSustainingMembers();
    }
}
