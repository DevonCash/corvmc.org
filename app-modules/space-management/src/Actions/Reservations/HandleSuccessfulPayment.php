<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use CorvMC\SpaceManagement\Enums\PaymentStatus;
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
     * Updates the reservation with payment details and automatically confirms it.
     * Also updates the associated Charge record.
     *
     * Idempotent - safe to call multiple times (e.g., from both redirect and webhook).
     */
    public function handle(RehearsalReservation $reservation, string $sessionId): bool
    {
        // Idempotency check - skip if already paid
        if (!$reservation->requiresPayment() && !$reservation->charge?->requiresPayment()) {
            return true;
        }

        $user = $reservation->getResponsibleUser();

        // Update reservation payment status (legacy fields)
        $reservation->update([
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => 'stripe',
            'paid_at' => now(),
            'payment_notes' => "Paid via Stripe (Session: {$sessionId})",
            'status' => ReservationStatus::Confirmed, // Automatically confirm paid reservations
        ]);

        // Update Charge record if exists
        if ($reservation->charge) {
            $reservation->charge->markAsPaid('stripe', $sessionId, "Paid via Stripe checkout");
        }

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
