<?php

namespace App\Actions\Notifications;

use App\Services\NotificationService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use NotificationService::getStats() instead
 * This action is maintained for backward compatibility only.
 * New code should use the NotificationService directly.
 */
class GetNotificationStats
{
    use AsAction;

    /**
     * @deprecated Use NotificationService::getStats() instead
     */
    public function handle(): array
    {
        return app(NotificationService::class)->getStats();
    }
}
