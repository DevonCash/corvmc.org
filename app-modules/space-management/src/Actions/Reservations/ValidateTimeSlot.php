<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class ValidateTimeSlot
{
    use AsAction;

    public const MIN_RESERVATION_DURATION = 1; // hours

    public const MAX_RESERVATION_DURATION = 8; // hours

    /**
     * Validate that a time slot is valid and available.
     *
     * Performs comprehensive validation including:
     * - Valid period (end after start)
     * - Business hours (9 AM - 10 PM)
     * - Duration limits (1-8 hours)
     * - Conflict detection (reservations and productions)
     *
     * Returns array with:
     * - valid: boolean
     * - errors: array of error messages
     * - conflicts: array with 'reservations' and 'productions' keys (only if conflicts exist)
     */
    public function handle(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): array
    {
        if ($endTime <= $startTime) {
            return [
                'valid' => false,
                'errors' => ['Invalid time period provided'],
            ];
        }

        // Check business hours
        $businessStart = $startTime->copy()->setTime(9, 0);
        $businessEnd = $startTime->copy()->setTime(22, 0);

        if ($startTime->lessThan($businessStart) || $endTime->greaterThan($businessEnd)) {
            return [
                'valid' => false,
                'errors' => ['Reservation must be within business hours (9 AM - 10 PM)'],
            ];
        }

        // Check minimum/maximum duration
        $duration = $startTime->diffInHours($endTime, true);
        if ($duration < self::MIN_RESERVATION_DURATION) {
            return [
                'valid' => false,
                'errors' => ['Minimum reservation duration is '.self::MIN_RESERVATION_DURATION.' hour'],
            ];
        }

        if ($duration > self::MAX_RESERVATION_DURATION) {
            return [
                'valid' => false,
                'errors' => ['Maximum reservation duration is '.self::MAX_RESERVATION_DURATION.' hours'],
            ];
        }

        // Check for conflicts using existing methods
        $conflicts = GetAllConflicts::run($startTime, $endTime, $excludeReservationId);
        $errors = [];

        if ($conflicts['reservations']->isNotEmpty()) {
            $errors[] = 'Conflicts with '.$conflicts['reservations']->count().' existing reservation(s)';
        }

        if ($conflicts['productions']->isNotEmpty()) {
            $errors[] = 'Conflicts with '.$conflicts['productions']->count().' production(s)';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'conflicts' => $conflicts,
        ];
    }
}
