<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use Carbon\Carbon;
use CorvMC\SpaceManagement\Services\ReservationService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use ReservationService::determineReservationStatus() instead
 * 
 * This action is maintained for backward compatibility.
 * New code should use the ReservationService directly.
 */
class DetermineReservationStatus
{
    use AsAction;

    /**
     * @deprecated Use ReservationService::determineReservationStatus() instead
     */
    public function handle(Carbon $reservationDate, bool $isRecurring = false): string
    {
        return app(ReservationService::class)->determineReservationStatus($reservationDate, $isRecurring);
    }

    /**
     * @deprecated Use ReservationService::needsConfirmationReminder() instead
     */
    public function needsConfirmationReminder(Carbon $reservationDate, bool $isRecurring = false): bool
    {
        return app(ReservationService::class)->needsConfirmationReminder($reservationDate, $isRecurring);
    }

    /**
     * @deprecated Use ReservationService::getConfirmationReminderDate() instead
     */
    public function getConfirmationReminderDate(Carbon $reservationDate): Carbon
    {
        return app(ReservationService::class)->getConfirmationReminderDate($reservationDate);
    }
}
