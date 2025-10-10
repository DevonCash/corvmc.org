<?php

namespace App\Actions\Reservations;

use App\Models\RehearsalReservation;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleFailedPayment
{
    use AsAction;

    /**
     * Handle failed or cancelled payment.
     *
     * Updates the reservation with payment failure details.
     */
    public function handle(RehearsalReservation $reservation, ?string $sessionId = null): void
    {
        $notes = $sessionId ? "Payment failed/cancelled (Session: {$sessionId})" : 'Payment cancelled by user';

        $reservation->update([
            'payment_status' => 'unpaid',
            'payment_notes' => $notes,
        ]);
    }
}
