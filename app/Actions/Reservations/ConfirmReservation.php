<?php

namespace App\Actions\Reservations;

use App\Models\RehearsalReservation;
use App\Notifications\ReservationConfirmedNotification;
use Lorisleiva\Actions\Concerns\AsAction;

class ConfirmReservation
{
    use AsAction;

    /**
     * Confirm a pending reservation.
     */
    public function handle(RehearsalReservation $reservation): RehearsalReservation
    {
        if ($reservation->status !== 'pending') {
            return $reservation;
        }

        $reservation->update(['status' => 'confirmed']);

        // Send confirmation notification
        $reservation->user->notify(new ReservationConfirmedNotification($reservation));

        return $reservation;
    }
}
