<?php

namespace App\Actions\Reservations;

use App\Models\Reservation;
use App\Notifications\ReservationCancelledNotification;
use Lorisleiva\Actions\Concerns\AsAction;

class CancelReservation
{
    use AsAction;

    /**
     * Cancel a reservation.
     */
    public function handle(Reservation $reservation, ?string $reason = null): Reservation
    {
        $reservation->update([
            'status' => 'cancelled',
            'notes' => $reservation->notes . ($reason ? "\nCancellation reason: " . $reason : ''),
        ]);

        // Send cancellation notification to responsible user
        $user = $reservation->getResponsibleUser();
        if ($user) {
            $user->notify(new ReservationCancelledNotification($reservation));
        }

        return $reservation;
    }
}
