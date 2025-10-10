<?php

namespace App\Http\Controllers;

use App\Facades\CreditService;
use App\Models\Reservation;
use App\Models\User;
use App\Services\UserSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

class StripeWebhookController extends CashierWebhookController
{
    public function __construct(
        private UserSubscriptionService $userSubscriptionService
    ) {}

    /**
     * Handle checkout session completed webhook.
     */
    public function handleCheckoutSessionCompleted(array $payload): SymfonyResponse
    {
        try {
            $session = $payload['data']['object'];
            $sessionId = $session['id'];
            $metadata = $session['metadata'] ?? [];

            // Handle different types of checkouts
            $checkoutType = $metadata['type'] ?? '';

            if ($checkoutType === 'practice_space_reservation') {
                return $this->handleReservationCheckout($session, $metadata);
            } elseif ($checkoutType === 'sliding_scale_membership') {
                return $this->handleSubscriptionCheckout($session, $metadata);
            } else {
                // Unknown checkout type, skip
                return $this->successMethod();
            }
        } catch (\Exception $e) {
            Log::error('Stripe webhook: Error processing checkout.session.completed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return response('Error processing webhook', 500);
        }
    }

    /**
     * Handle reservation checkout completion.
     */
    private function handleReservationCheckout(array $session, array $metadata): SymfonyResponse
    {
        try {
            $sessionId = $session['id'];
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
            \App\Actions\Reservations\HandleSuccessfulPayment::run($reservation, $sessionId);

            Log::info('Stripe webhook: Successfully processed reservation payment', [
                'reservation_id' => $reservationId,
                'session_id' => $sessionId,
                'amount' => $reservation->cost,
            ]);
        } catch (\Exception $e) {
            Log::error('Stripe webhook: Error processing reservation checkout', [
                'error' => $e->getMessage(),
                'session_id' => $session['id'] ?? 'unknown',
            ]);

            return response('Error processing webhook', 500);
        }

        return $this->successMethod();
    }

    /**
     * Handle subscription checkout completion.
     * Note: Actual subscription creation/updates are handled by Cashier automatically.
     * We only need to update our membership status.
     */
    private function handleSubscriptionCheckout(array $session, array $metadata): SymfonyResponse
    {
        try {
            $sessionId = $session['id'];
            $userId = $metadata['user_id'] ?? null;

            if (!$userId) {
                Log::warning('Stripe webhook: No user ID in subscription metadata', ['session_id' => $sessionId]);
                return $this->successMethod();
            }

            $user = User::find($userId);

            if (!$user) {
                Log::error('Stripe webhook: User not found for subscription', ['user_id' => $userId]);
                return $this->successMethod();
            }

            // Update user membership status (Cashier handles the subscription sync automatically)
            $this->userSubscriptionService->updateUserMembershipStatus($user);

            \App\Actions\MemberBenefits\AllocateUserMonthlyCredits::run($user);

            Log::info('Stripe webhook: Successfully processed subscription checkout', [
                'user_id' => $userId,
                'session_id' => $sessionId,
                'base_amount' => $metadata['base_amount'] ?? null,
                'covers_fees' => $metadata['covers_fees'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Stripe webhook: Error processing subscription checkout', [
                'error' => $e->getMessage(),
                'session_id' => $session['id'] ?? 'unknown',
            ]);

            return response('Error processing webhook', 500);
        }

        return $this->successMethod();
    }

    /**
     * Handle payment intent payment failed webhook.
     */
    public function handlePaymentIntentPaymentFailed(array $payload): SymfonyResponse
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
            \App\Actions\Reservations\HandleFailedPayment::run($reservation);

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

    /**
     * Override Cashier's subscription created handler to add our membership logic.
     */
    protected function handleCustomerSubscriptionCreated(array $payload): SymfonyResponse
    {
        // Let Cashier handle the subscription creation first
        $response = parent::handleCustomerSubscriptionCreated($payload);

        try {
            $subscription = $payload['data']['object'];
            $customerId = $subscription['customer'];

            // Find user by Stripe customer ID
            $user = User::where('stripe_id', $customerId)->first();

            if ($user && $subscription['status'] === 'active') {
                // Update membership status since Cashier created the subscription
                $this->userSubscriptionService->updateUserMembershipStatus($user);

                Log::info('Stripe webhook: Updated membership status after subscription creation', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription['id'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Stripe webhook: Error updating membership status after subscription creation', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }

        return $response;
    }

    /**
     * Override Cashier's subscription updated handler to add our membership logic.
     */
    protected function handleCustomerSubscriptionUpdated(array $payload): SymfonyResponse
    {
        // Let Cashier handle the subscription update first
        $response = parent::handleCustomerSubscriptionUpdated($payload);

        try {
            $subscription = $payload['data']['object'];
            $customerId = $subscription['customer'];

            // Find user by Stripe customer ID
            $user = User::where('stripe_id', $customerId)->first();

            if ($user) {
                // Update membership status since Cashier updated the subscription
                $this->userSubscriptionService->updateUserMembershipStatus($user);

                Log::info('Stripe webhook: Updated membership status after subscription update', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription['id'],
                    'status' => $subscription['status'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Stripe webhook: Error updating membership status after subscription update', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }

        return $response;
    }

    /**
     * Override Cashier's subscription deleted handler to add our membership logic.
     */
    protected function handleCustomerSubscriptionDeleted(array $payload): SymfonyResponse
    {
        // Let Cashier handle the subscription deletion first
        $response = parent::handleCustomerSubscriptionDeleted($payload);

        try {
            $subscription = $payload['data']['object'];
            $customerId = $subscription['customer'];

            // Find user by Stripe customer ID
            $user = User::where('stripe_id', $customerId)->first();

            if ($user) {
                // Update membership status since subscription was deleted
                $this->userSubscriptionService->updateUserMembershipStatus($user);

                Log::info('Stripe webhook: Updated membership status after subscription deletion', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription['id'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Stripe webhook: Error updating membership status after subscription deletion', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }

        return $response;
    }

    /**
     * Override Cashier's invoice payment succeeded handler to add our membership logic.
     */
    protected function handleInvoicePaymentSucceeded(array $payload): SymfonyResponse
    {
        try {
            $invoice = $payload['data']['object'];
            $customerId = $invoice['customer'];

            // Find user by Stripe customer ID
            $user = User::where('stripe_id', $customerId)->first();

            if ($user) {
                // Update membership status in case this payment qualifies them
                $this->userSubscriptionService->updateUserMembershipStatus($user);

                // Allocate monthly credits if they're a sustaining member
                // This is idempotent - won't double-allocate in same month
                if ($user->hasRole('sustaining member')) {
                    \App\Actions\MemberBenefits\AllocateUserMonthlyCredits::run($user);

                    Log::info('Stripe webhook: Allocated monthly credits after invoice payment', [
                        'user_id' => $user->id,
                        'invoice_id' => $invoice['id'],
                    ]);
                }

                Log::info('Stripe webhook: Updated membership status after invoice payment', [
                    'user_id' => $user->id,
                    'invoice_id' => $invoice['id'],
                    'amount_paid' => $invoice['amount_paid'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Stripe webhook: Error updating membership status after invoice payment', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }

        return $this->successMethod();
    }
}
