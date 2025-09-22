<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use App\Services\UserSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

class StripeWebhookController extends CashierWebhookController
{
    public function __construct(
        private ReservationService $reservationService,
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
        
        return $this->successMethod();
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
            $this->reservationService->handleSuccessfulPayment($reservation, $sessionId);
            
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
            
            // Get the subscription ID from the session
            $subscriptionId = $session['subscription'] ?? null;
            
            if ($subscriptionId) {
                // Retrieve the subscription to get the latest data with metadata
                $stripeSubscription = Cashier::stripe()->subscriptions->retrieve($subscriptionId);
                
                // Sync the subscription with our local database
                $this->userSubscriptionService->syncStripeSubscription($user, $stripeSubscription->toArray());
                
                // Update user membership status
                $this->userSubscriptionService->updateUserMembershipStatus($user);
                
                Log::info('Stripe webhook: Successfully processed subscription checkout', [
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'subscription_id' => $subscriptionId,
                    'base_amount' => $metadata['base_amount'] ?? null,
                    'covers_fees' => $metadata['covers_fees'] ?? null,
                ]);
            } else {
                Log::warning('Stripe webhook: No subscription ID in checkout session', ['session_id' => $sessionId]);
            }
            
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

    /**
     * Handle customer subscription created webhook.
     */
    public function handleCustomerSubscriptionCreated(array $payload): SymfonyResponse
    {
        try {
            $subscription = $payload['data']['object'];
            $customerId = $subscription['customer'];
            
            // Find user by Stripe customer ID
            $user = User::where('stripe_id', $customerId)->first();
            
            if (!$user) {
                Log::warning('Stripe webhook: User not found for customer', ['customer_id' => $customerId]);
                return $this->successMethod();
            }
            
            // Create or update Cashier subscription
            $this->userSubscriptionService->syncStripeSubscription($user, $subscription);
            
            // Assign sustaining member role if subscription is active and meets criteria
            if ($subscription['status'] === 'active') {
                $this->userSubscriptionService->updateUserMembershipStatus($user);
            }
            
            Log::info('Stripe webhook: Successfully processed subscription creation', [
                'user_id' => $user->id,
                'subscription_id' => $subscription['id'],
                'status' => $subscription['status'],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Stripe webhook: Error processing customer.subscription.created', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            
            return response('Error processing webhook', 500);
        }
        
        return $this->successMethod();
    }

    /**
     * Handle customer subscription updated webhook.
     */
    public function handleCustomerSubscriptionUpdated(array $payload): SymfonyResponse
    {
        try {
            $subscription = $payload['data']['object'];
            $customerId = $subscription['customer'];
            
            // Find user by Stripe customer ID
            $user = User::where('stripe_id', $customerId)->first();
            
            if (!$user) {
                Log::warning('Stripe webhook: User not found for customer', ['customer_id' => $customerId]);
                return $this->successMethod();
            }
            
            // Update Cashier subscription
            $this->userSubscriptionService->syncStripeSubscription($user, $subscription);
            
            // Update membership status based on subscription status
            $this->userSubscriptionService->updateUserMembershipStatus($user);
            
            Log::info('Stripe webhook: Successfully processed subscription update', [
                'user_id' => $user->id,
                'subscription_id' => $subscription['id'],
                'status' => $subscription['status'],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Stripe webhook: Error processing customer.subscription.updated', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            
            return response('Error processing webhook', 500);
        }
        
        return $this->successMethod();
    }

    /**
     * Handle customer subscription deleted webhook.
     */
    public function handleCustomerSubscriptionDeleted(array $payload): SymfonyResponse
    {
        try {
            $subscription = $payload['data']['object'];
            $customerId = $subscription['customer'];
            
            // Find user by Stripe customer ID
            $user = User::where('stripe_id', $customerId)->first();
            
            if (!$user) {
                Log::warning('Stripe webhook: User not found for customer', ['customer_id' => $customerId]);
                return $this->successMethod();
            }
            
            // Update membership status (remove sustaining member role if no other qualifying factors)
            $this->userSubscriptionService->updateUserMembershipStatus($user);
            
            Log::info('Stripe webhook: Successfully processed subscription deletion', [
                'user_id' => $user->id,
                'subscription_id' => $subscription['id'],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Stripe webhook: Error processing customer.subscription.deleted', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            
            return response('Error processing webhook', 500);
        }
        
        return $this->successMethod();
    }

    /**
     * Handle invoice payment succeeded webhook.
     */
    public function handleInvoicePaymentSucceeded(array $payload): SymfonyResponse
    {
        try {
            $invoice = $payload['data']['object'];
            $customerId = $invoice['customer'];
            
            // Find user by Stripe customer ID
            $user = User::where('stripe_id', $customerId)->first();
            
            if (!$user) {
                Log::warning('Stripe webhook: User not found for customer', ['customer_id' => $customerId]);
                return $this->successMethod();
            }
            
            // Update membership status in case this payment qualifies them
            $this->userSubscriptionService->updateUserMembershipStatus($user);
            
            Log::info('Stripe webhook: Successfully processed invoice payment', [
                'user_id' => $user->id,
                'invoice_id' => $invoice['id'],
                'amount_paid' => $invoice['amount_paid'],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Stripe webhook: Error processing invoice.payment_succeeded', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            
            return response('Error processing webhook', 500);
        }
        
        return $this->successMethod();
    }
}
