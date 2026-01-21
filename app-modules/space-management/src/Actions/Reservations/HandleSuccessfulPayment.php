<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use App\Actions\GoogleCalendar\SyncReservationToGoogleCalendar;
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
     * Idempotent - safe to call multiple times (e.g., from both redirect and webhook).
     */
    public function handle(RehearsalReservation $reservation, string $sessionId): bool
    {
        // Idempotency check - skip if already paid
        if (!$reservation->requiresPayment()) {
            return true;
        }

        $user = $reservation->getResponsibleUser();

        // Update reservation payment status
        $reservation->update([
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => 'stripe',
            'paid_at' => now(),
            'payment_notes' => "Paid via Stripe (Session: {$sessionId})",
            'status' => ReservationStatus::Confirmed, // Automatically confirm paid reservations
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

        // Sync to Google Calendar - don't let sync failures affect the payment
        try {
            SyncReservationToGoogleCalendar::run($reservation, 'update');
        } catch (\Exception $e) {
            \Log::error('Failed to sync paid reservation to Google Calendar', [
                'reservation_id' => $reservation->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }
}
