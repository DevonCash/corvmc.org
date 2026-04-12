<?php

namespace CorvMC\SpaceManagement\Actions\RecurringReservations;

use Carbon\Carbon;
use CorvMC\SpaceManagement\Services\RecurringReservationService;

/**
 * @deprecated Use RecurringReservationService::validateRecurringPattern() instead.
 * This action will be removed in a future version.
 * 
 * The business logic has been moved to RecurringReservationService for better
 * organization and testability. This action now delegates to the service.
 */
class ValidateRecurringPattern
{
    /**
     * Validate a recurring pattern for conflicts.
     *
     * Returns an array with 'errors' and 'warnings' collections.
     * Each item contains 'date', 'time', and 'conflicts' keys.
     *
     * @param  int  $checkOccurrences  Number of occurrences to check
     */
    public function handle(
        string $recurrenceRule,
        Carbon $seriesStartDate,
        ?Carbon $seriesEndDate,
        string $startTime,
        string $endTime,
        int $checkOccurrences = 8,
        ?int $excludeSeriesId = null,
    ): array {
        return app(RecurringReservationService::class)->validateRecurringPattern(
            $recurrenceRule,
            $seriesStartDate,
            $seriesEndDate,
            $startTime,
            $endTime,
            $checkOccurrences,
            $excludeSeriesId
        );
    }
}
