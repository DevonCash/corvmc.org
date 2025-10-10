<?php

namespace App\Actions\Reservations;

use App\Models\RehearsalReservation;
use App\Notifications\ReservationCancelledNotification;
use Lorisleiva\Actions\Concerns\AsAction;

class CancelReservation
{
    use AsAction;

    /**
     * Cancel a reservation.
     */
    public function handle(RehearsalReservation $reservation, ?string $reason = null): RehearsalReservation
    {
        $reservation->update([
            'status' => 'cancelled',
            'notes' => $reservation->notes . ($reason ? "\nCancellation reason: " . $reason : ''),
        ]);

        // Send cancellation notification
        $reservation->user->notify(new ReservationCancelledNotification($reservation));

        return $reservation;
    }
}
