<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleFailedPayment
{
    use AsAction;

    /**
     * Handle failed or cancelled payment.
     *
     * The Charge record remains in Pending status - no update needed.
     * This just logs the failure for debugging purposes.
     */
    public function handle(RehearsalReservation $reservation, ?string $sessionId = null): void
    {
        $notes = $sessionId ? "Payment failed/cancelled (Session: {$sessionId})" : 'Payment cancelled by user';

        // Log the failure on the charge if it exists
        if ($reservation->charge) {
            $reservation->charge->update([
                'notes' => $notes,
            ]);
        }
    }
}
