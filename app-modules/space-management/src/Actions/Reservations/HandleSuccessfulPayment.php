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
     * 
     * @param RehearsalReservation $reservation
     * @param string $sessionId The Stripe checkout session ID
     * @param string|null $paymentIntentId The Stripe payment intent ID
     */
    public function handle(RehearsalReservation $reservation, string $sessionId, ?string $paymentIntentId = null): bool
    {
        // Idempotency check - skip if already paid
        if ($reservation->isPaid()) {
            return true;
        }

        $user = $reservation->getResponsibleUser();

        // Update Charge record
        $reservation->charge?->markAsPaid('stripe', $sessionId, $paymentIntentId, "Paid via Stripe checkout");

        activity('reservation')
            ->performedOn($reservation)
            ->causedBy($user)
            ->event('payment_recorded')
            ->withProperties([
                'payment_method' => 'stripe',
                'session_id' => $sessionId,
            ])
            ->log('Payment completed via Stripe checkout');

        // Confirm the reservation
        $reservation->update([
            'status' => ReservationStatus::Confirmed,
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
