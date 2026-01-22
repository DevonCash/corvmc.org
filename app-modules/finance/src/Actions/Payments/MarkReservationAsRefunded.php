<?php

namespace CorvMC\Finance\Actions\Payments;

use CorvMC\SpaceManagement\Enums\PaymentStatus;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkReservationAsRefunded
{
    use AsAction;

    public function handle(Reservation $reservation, ?string $notes = null): void
    {
        // Update legacy fields on reservation
        $reservation->update([
            'payment_status' => PaymentStatus::Refunded,
            'paid_at' => now(),
            'payment_notes' => $notes,
        ]);

        // Update Charge record if exists
        if ($reservation instanceof RehearsalReservation && $reservation->charge) {
            $reservation->charge->markAsRefunded($notes);
        }
    }
}
