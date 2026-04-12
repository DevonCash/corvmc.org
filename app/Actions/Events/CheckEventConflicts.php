<?php

namespace App\Actions\Events;

use App\Services\EventSyncService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use EventSyncService::checkConflicts() instead
 * This action is maintained for backward compatibility only.
 * New code should use the EventSyncService directly.
 */
class CheckEventConflicts
{
    use AsAction;

    /**
     * @deprecated Use EventSyncService::checkConflicts() instead
     */
    public function handle(...$args)
    {
        return app(EventSyncService::class)->checkConflicts(...$args);
    }
}
