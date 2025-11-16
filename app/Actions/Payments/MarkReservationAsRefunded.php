<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Reservation;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkReservationAsRefunded
{
    use AsAction;

    public function handle(Reservation $reservation, ?string $notes = null): void
    {
        $reservation->update([
            'payment_status' => PaymentStatus::Refunded,
            'paid_at' => now(),
            'payment_notes' => $notes,
        ]);
    }
}
