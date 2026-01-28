<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use App\Models\User;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class ValidateReservation
{
    use AsAction;

    public const MIN_RESERVATION_DURATION = 1; // hours

    public const MAX_RESERVATION_DURATION = 8; // hours

    /**
     * Validate reservation parameters.
     */
    public function handle(User $user, Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): array
    {
        $errors = [];

        // Check if start time is in the future
        if ($startTime->isPast()) {
            $errors[] = 'Reservation start time must be in the future.';
        }

        // Require advance notice - no same-day reservations
        if ($startTime->isToday()) {
            $errors[] = 'Same-day reservations are not allowed. Please schedule for tomorrow or later.';
        }

        // Check if end time is after start time
        if ($endTime->lte($startTime)) {
            $errors[] = 'End time must be after start time.';
        }

        $hours = $startTime->diffInMinutes($endTime) / 60;

        // Check minimum duration
        if ($hours < self::MIN_RESERVATION_DURATION) {
            $errors[] = 'Minimum reservation duration is '.self::MIN_RESERVATION_DURATION.' hour(s).';
        }

        // Check maximum duration
        if ($hours > self::MAX_RESERVATION_DURATION) {
            $errors[] = 'Maximum reservation duration is '.self::MAX_RESERVATION_DURATION.' hours.';
        }

        // Check for conflicts
        if (! CheckTimeSlotAvailability::run($startTime, $endTime, $excludeReservationId)) {
            $allConflicts = GetAllConflicts::run($startTime, $endTime, $excludeReservationId);
            $conflictMessages = [];

            if ($allConflicts['reservations']->isNotEmpty()) {
                $reservationConflicts = $allConflicts['reservations']->map(function ($r) {
                    $userName = $r->user?->name ?? $r->reservable?->name ?? 'Unknown';

                    return $userName.' ('.$r->reserved_at->format('M j, g:i A').' - '.$r->reserved_until->format('g:i A').')';
                })->join(', ');
                $conflictMessages[] = 'existing reservation(s): '.$reservationConflicts;
            }

            if ($allConflicts['productions']->isNotEmpty()) {
                $productionConflicts = $allConflicts['productions']->map(function ($p) {
                    return $p->title.' ('.$p->start_time->format('M j, g:i A').' - '.$p->end_time->format('g:i A').')';
                })->join(', ');
                $conflictMessages[] = 'production(s): '.$productionConflicts;
            }

            $errors[] = 'Time slot conflicts with '.implode(' and ', $conflictMessages);
        }

        // Business hours check (9 AM to 10 PM)
        if ($startTime->hour < 9 || $endTime->hour > 22 || ($endTime->hour == 22 && $endTime->minute > 0)) {
            $errors[] = 'Reservations are only allowed between 9 AM and 10 PM.';
        }

        return $errors;
    }
}
