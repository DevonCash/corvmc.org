<?php

namespace App\Actions\Events;

use App\Services\EventSyncService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use EventSyncService::syncSpaceReservation() instead
 * This action is maintained for backward compatibility only.
 * New code should use the EventSyncService directly.
 */
class SyncEventSpaceReservation
{
    use AsAction;

    /**
     * @deprecated Use EventSyncService::syncSpaceReservation() instead
     */
    public function handle(...$args)
    {
        return app(EventSyncService::class)->syncSpaceReservation(...$args);
    }
}
