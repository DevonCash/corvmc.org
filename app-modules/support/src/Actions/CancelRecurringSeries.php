<?php

namespace CorvMC\Support\Actions;

use CorvMC\Support\Models\RecurringSeries;
use CorvMC\Support\Services\RecurringService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Cancel a recurring series and all its future instances.
 *
 * @deprecated Use RecurringService::cancelSeries() instead
 * This action is maintained for backward compatibility only.
 * New code should use the RecurringService directly.
 */
class CancelRecurringSeries
{
    use AsAction;

    /**
     * Cancel entire recurring series and all future instances.
     * 
     * @deprecated Use RecurringService::cancelSeries() instead
     */
    public function handle(RecurringSeries $series, ?string $reason = null): void
    {
        app(RecurringService::class)->cancelSeries($series, $reason);
    }
}
