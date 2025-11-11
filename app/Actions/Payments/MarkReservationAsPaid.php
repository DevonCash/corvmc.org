<?php

namespace App\Actions\Payments;

use App\Actions\Reservations\ConfirmReservation;
use App\Models\RehearsalReservation;
use App\Models\Reservation;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkReservationAsPaid
{
    use AsAction;

    public function handle(Reservation $reservation, ?string $paymentMethod = null, ?string $notes = null): void
    {
        // If the reservation is pending, confirm it first
        if ($reservation instanceof RehearsalReservation && $reservation->status === 'pending') {
            $reservation = ConfirmReservation::run($reservation);
            $reservation->refresh();
        }

        $reservation->update([
            'payment_status' => 'paid',
            'payment_method' => $paymentMethod,
            'paid_at' => now(),
            'payment_notes' => $notes,
        ]);
    }
}
