<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Notifications\ReservationConfirmedNotification;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleSuccessfulPayment
{
    use AsAction;

    /**
     * Handle successful payment and update reservation.
     *
     * Updates the Charge record and automatically confirms the reservation.
     *
     * Idempotent - safe to call multiple times (e.g., from both redirect and webhook).
     */
    public function handle(RehearsalReservation $reservation, string $sessionId): bool
    {
        // Idempotency check - skip if already paid
        if ($reservation->charge && !$reservation->charge->requiresPayment()) {
            return true;
        }
        if ($reservation->payment_status === 'paid') {
            return true;
        }

        $user = $reservation->getResponsibleUser();

        // Update Charge record
        if ($reservation->charge) {
            $reservation->charge->markAsPaid('stripe', $sessionId, "Paid via Stripe checkout");
        }

        // Confirm the reservation
        $reservation->update([
            'status' => ReservationStatus::Confirmed,
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'paid_at' => now(),
            'payment_notes' => "Checkout session: {$sessionId}",
        ]);

        $reservation->refresh();

        // Send notification outside transaction - don't let email failures affect the payment
        try {
            $user->notify(new ReservationConfirmedNotification($reservation));
        } catch (\Exception $e) {
            \Log::error('Failed to send reservation confirmation email after payment', [
                'reservation_id' => $reservation->id,
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }
}
