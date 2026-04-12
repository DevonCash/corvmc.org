<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use App\Filament\Actions\Reservations\ReservationConfirmAction;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Services\ReservationService;
use Filament\Actions\Action;

/**
 * @deprecated Use ReservationService::confirm() instead
 * 
 * This action is maintained for backward compatibility.
 * New code should use the ReservationService directly.
 */
class ConfirmReservation
{
    /**
     * @deprecated Use ReservationService::checkConfirmationReadiness() instead
     */
    public static function canConfirm($reservation): bool
    {
        $result = app(ReservationService::class)->checkConfirmationReadiness($reservation);
        return $result['can_confirm'];
    }

    /**
     * @deprecated Use ReservationService::confirm() instead
     */
    public function handle(RehearsalReservation $reservation, bool $notify_user = true): RehearsalReservation
    {
        return app(ReservationService::class)->confirm($reservation, $notify_user);
    }

    /**
     * @deprecated Use ReservationConfirmAction::make() instead
     * 
     * Get the Filament action for confirming a reservation.
     */
    public static function filamentAction(): Action
    {
        return ReservationConfirmAction::make();
    }
}
