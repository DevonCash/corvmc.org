<?php

namespace App\Actions\Payments;

use App\Models\Reservation;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckRequiresPayment
{
    use AsAction;

    /**
     * Determine if a reservation requires payment.
     */
    public function handle(Reservation $reservation): bool
    {
        return $reservation->cost->isPositive()
            && $reservation->payment_status !== 'paid'
            && $reservation->payment_status !== 'comped';
    }
}
