<?php

namespace CorvMC\Support\Actions;

use CorvMC\Support\Models\RecurringSeries;
use CorvMC\Support\Services\RecurringService;
use Illuminate\Support\Collection;

/**
 * Generate instances (Reservations, Events, etc.) for a recurring series.
 *
 * @deprecated Use RecurringService::generateInstances() instead
 * This action is maintained for backward compatibility only.
 * New code should use the RecurringService directly.
 */
class GenerateRecurringInstances
{
    /**
     * Generate instances for a recurring series.
     * Only generates up to max_advance_days into the future.
     * 
     * @deprecated Use RecurringService::generateInstances() instead
     */
    public function handle(RecurringSeries $series): Collection
    {
        return app(RecurringService::class)->generateInstances($series);
    }
}
