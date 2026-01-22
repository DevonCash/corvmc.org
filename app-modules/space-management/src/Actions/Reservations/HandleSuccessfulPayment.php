<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use App\Enums\ReservationStatus as AppReservationStatus;
use App\Models\RehearsalReservation as AppRehearsalReservation;
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
    public function handle(RehearsalReservation|AppRehearsalReservation $reservation, string $sessionId): bool
    {
        // Idempotency check - skip if already paid
        // For module model, check the charge record
        if ($reservation instanceof RehearsalReservation && $reservation->charge && !$reservation->charge->requiresPayment()) {
            return true;
        }
        // For app model, check payment_status directly
        if ($reservation instanceof AppRehearsalReservation && $reservation->payment_status === 'paid') {
            return true;
        }

        $user = $reservation->getResponsibleUser();

        // Update Charge record (only for module model)
        if ($reservation instanceof RehearsalReservation && $reservation->charge) {
            $reservation->charge->markAsPaid('stripe', $sessionId, "Paid via Stripe checkout");
        }

        // Confirm the reservation using appropriate status enum
        $confirmedStatus = $reservation instanceof AppRehearsalReservation
            ? AppReservationStatus::Confirmed
            : ReservationStatus::Confirmed;

        $reservation->update([
            'status' => $confirmedStatus,
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'paid_at' => now(),
            'payment_notes' => "Checkout session: {$sessionId}",
        ]);

        $reservation->refresh();

        // Send notification outside transaction - don't let email failures affect the payment
        // Only send module notification for module model; app has its own notifications
        if ($reservation instanceof RehearsalReservation) {
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
        }

        return true;
    }
}
