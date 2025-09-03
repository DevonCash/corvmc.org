<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

class StripeWebhookController extends CashierWebhookController
{
    public function __construct(
        private ReservationService $reservationService
    ) {}

    /**
     * Handle checkout session completed webhook.
     */
    public function handleCheckoutSessionCompleted(array $payload): Response
    {
        try {
            $session = $payload['data']['object'];
            $sessionId = $session['id'];
            $metadata = $session['metadata'] ?? [];
            
            // Only handle reservation payments
            if (($metadata['type'] ?? '') !== 'practice_space_reservation') {
                return $this->successMethod();
            }
            
            $reservationId = $metadata['reservation_id'] ?? null;
            
            if (!$reservationId) {
                Log::warning('Stripe webhook: No reservation ID in metadata', ['session_id' => $sessionId]);
                return $this->successMethod();
            }
            
            $reservation = Reservation::find($reservationId);
            
            if (!$reservation) {
                Log::error('Stripe webhook: Reservation not found', ['reservation_id' => $reservationId]);
                return $this->successMethod();
            }
            
            // Skip if already paid to avoid duplicate processing
            if ($reservation->isPaid()) {
                Log::info('Stripe webhook: Reservation already paid', ['reservation_id' => $reservationId]);
                return $this->successMethod();
            }
            
            // Process the successful payment
            $this->reservationService->handleSuccessfulPayment($reservation, $sessionId);
            
            Log::info('Stripe webhook: Successfully processed reservation payment', [
                'reservation_id' => $reservationId,
                'session_id' => $sessionId,
                'amount' => $reservation->cost,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Stripe webhook: Error processing checkout.session.completed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            
            return response('Error processing webhook', 500);
        }
        
        return $this->successMethod();
    }

    /**
     * Handle payment intent payment failed webhook.
     */
    public function handlePaymentIntentPaymentFailed(array $payload): Response
    {
        try {
            $paymentIntent = $payload['data']['object'];
            $metadata = $paymentIntent['metadata'] ?? [];
            
            // Only handle reservation payments
            if (($metadata['type'] ?? '') !== 'practice_space_reservation') {
                return $this->successMethod();
            }
            
            $reservationId = $metadata['reservation_id'] ?? null;
            
            if (!$reservationId) {
                Log::warning('Stripe webhook: No reservation ID in payment failure metadata');
                return $this->successMethod();
            }
            
            $reservation = Reservation::find($reservationId);
            
            if (!$reservation) {
                Log::error('Stripe webhook: Reservation not found for payment failure', ['reservation_id' => $reservationId]);
                return $this->successMethod();
            }
            
            // Handle failed payment
            $this->reservationService->handleFailedPayment($reservation);
            
            Log::info('Stripe webhook: Processed payment failure', [
                'reservation_id' => $reservationId,
                'payment_intent_id' => $paymentIntent['id'],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Stripe webhook: Error processing payment_intent.payment_failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }
        
        return $this->successMethod();
    }
}
