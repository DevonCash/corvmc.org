<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use CorvMC\SpaceManagement\Enums\PaymentStatus;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
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
            'payment_status' => PaymentStatus::Unpaid,
            'payment_notes' => $notes,
        ]);
    }
}
