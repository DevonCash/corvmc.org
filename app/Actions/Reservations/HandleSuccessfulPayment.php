<?php

namespace App\Actions\Reservations;

use App\Models\RehearsalReservation;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleSuccessfulPayment
{
    use AsAction;

    /**
     * Handle successful payment and update reservation.
     *
     * Updates the reservation with payment details and automatically confirms it.
     */
    public function handle(RehearsalReservation $reservation, string $sessionId): bool
    {
        // Update reservation payment status
        $reservation->update([
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'paid_at' => now(),
            'payment_notes' => "Paid via Stripe (Session: {$sessionId})",
            'status' => 'confirmed', // Automatically confirm paid reservations
        ]);

        return true;
    }
}
