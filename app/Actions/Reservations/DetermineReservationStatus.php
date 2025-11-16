<?php

namespace App\Actions\Reservations;

use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class DetermineReservationStatus
{
    use AsAction;

    /**
     * Determine the initial status for a reservation based on business rules.
     *
     * Business rules:
     * - Recurring reservations always need manual approval (pending)
     * - Reservations more than a week away need confirmation reminder (pending)
     * - Near-term reservations are immediately confirmed
     */
    public function handle(Carbon $reservationDate, bool $isRecurring = false): string
    {
        // Recurring reservations always need manual approval
        if ($isRecurring) {
            return 'pending';
        }

        // Reservations more than a week away need confirmation reminder
        if ($reservationDate->isAfter(Carbon::now()->addWeek())) {
            return 'pending';
        }

        // Near-term reservations are immediately confirmed
        return 'confirmed';
    }

    /**
     * Check if a reservation needs a confirmation reminder.
     */
    public function needsConfirmationReminder(Carbon $reservationDate, bool $isRecurring = false): bool
    {
        return ! $isRecurring && $reservationDate->isAfter(Carbon::now()->addWeek());
    }

    /**
     * Calculate when to send the confirmation reminder (1 week before).
     */
    public function getConfirmationReminderDate(Carbon $reservationDate): Carbon
    {
        return $reservationDate->copy()->subWeek();
    }
}
