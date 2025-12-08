<?php

namespace App\Actions\Reservations;

use App\Enums\PaymentStatus;
use App\Models\Reservation;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessReservationCheckout
{
    use AsAction;

    /**
     * Process a successful reservation checkout.
     *
     * This is called from both the checkout success redirect and Stripe webhooks.
     * Idempotent - safe to call multiple times for the same reservation.
     *
     * @param  int  $reservationId  The reservation ID from metadata
     * @param  string  $sessionId  The Stripe checkout session ID
     * @return bool Whether processing was successful
     */
    public function handle(int $reservationId, string $sessionId): bool
    {
        try {
            if (! $reservationId) {
                Log::warning('No reservation ID provided for checkout processing', ['session_id' => $sessionId]);

                return false;
            }

            $reservation = Reservation::find($reservationId);

            if (! $reservation) {
                Log::error('Reservation not found for checkout', [
                    'reservation_id' => $reservationId,
                    'session_id' => $sessionId,
                ]);

                return false;
            }

            // Skip if already paid (idempotency check)
            if ($reservation->payment_status == PaymentStatus::Paid) {
                Log::info('Reservation already paid, skipping', [
                    'reservation_id' => $reservationId,
                    'session_id' => $sessionId,
                ]);

                return true;
            }

            // Process the successful payment (this action is also idempotent)
            HandleSuccessfulPayment::run($reservation, $sessionId);

            Log::info('Successfully processed reservation checkout', [
                'reservation_id' => $reservationId,
                'session_id' => $sessionId,
                'amount' => $reservation->cost,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error processing reservation checkout', [
                'error' => $e->getMessage(),
                'reservation_id' => $reservationId,
                'session_id' => $sessionId,
            ]);

            return false;
        }
    }
}
