<?php

namespace CorvMC\Finance\Actions\Subscriptions;

use CorvMC\Finance\Services\SubscriptionService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use SubscriptionService::processSubscriptionCheckout() instead
 * This action is maintained for backward compatibility only.
 * New code should use the SubscriptionService directly.
 */
class ProcessSubscriptionCheckout
{
    use AsAction;

    /**
     * @deprecated Use SubscriptionService::processSubscriptionCheckout() instead
     */
    public function handle(int $userId, string $sessionId, array $metadata = []): bool
    {
        return app(SubscriptionService::class)->processSubscriptionCheckout($userId, $sessionId, $metadata);
    }
}
