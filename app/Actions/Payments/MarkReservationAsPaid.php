<?php

namespace App\Actions\Payments;

use App\Models\Reservation;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkReservationAsPaid
{
    use AsAction;

    public function handle(Reservation $reservation, ?string $paymentMethod = null, ?string $notes = null): void
    {
        $reservation->update([
            'payment_status' => 'paid',
            'payment_method' => $paymentMethod,
            'paid_at' => now(),
            'payment_notes' => $notes,
        ]);
    }
}
