<?php

namespace CorvMC\Support\Actions;

use CorvMC\Support\Contracts\Recurrable;
use CorvMC\Support\Models\RecurringSeries;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Cancel a recurring series and all its future instances.
 *
 * This action calls static methods on the recurable_type class directly.
 * The model must implement the Recurrable interface.
 */
class CancelRecurringSeries
{
    use AsAction;

    /**
     * Cancel entire recurring series and all future instances.
     */
    public function handle(RecurringSeries $series, ?string $reason = null): void
    {
        $recurableType = Relation::getMorphedModel($series->recurable_type) ?? $series->recurable_type;

        if (! is_a($recurableType, Recurrable::class, true)) {
            throw new \RuntimeException(
                "Model {$recurableType} must implement ".Recurrable::class
            );
        }

        DB::transaction(function () use ($series, $recurableType, $reason) {
            // Cancel the series itself
            $series->update(['status' => 'cancelled']);

            // Cancel all future instances
            $recurableType::cancelFutureInstances($series, $reason);
        });
    }
}
