<?php

namespace CorvMC\Support\Contracts;

use Carbon\Carbon;
use CorvMC\Support\Models\RecurringSeries;

/**
 * Contract for models that can be created from a RecurringSeries.
 *
 * Models implementing this interface can be automatically generated
 * as instances of a recurring series (e.g., RehearsalReservation, Event).
 */
interface Recurrable
{
    /**
     * Create a single instance for the given date from a recurring series.
     *
     * @throws \InvalidArgumentException If the instance cannot be created (e.g., conflict)
     */
    public static function createFromRecurringSeries(RecurringSeries $series, Carbon $date): static;

    /**
     * Create a cancelled placeholder to track a skipped instance.
     * Called when createFromRecurringSeries throws an InvalidArgumentException.
     */
    public static function createCancelledPlaceholder(RecurringSeries $series, Carbon $date): void;

    /**
     * Check if an instance already exists for this date in the series.
     */
    public static function instanceExistsForDate(RecurringSeries $series, Carbon $date): bool;

    /**
     * Cancel all future instances for a series.
     *
     * @return int The number of instances cancelled
     */
    public static function cancelFutureInstances(RecurringSeries $series, ?string $reason = null): int;
}
