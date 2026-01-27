<?php

namespace App\Http\Controllers;

use CorvMC\Finance\Actions\Subscriptions\UpdateUserMembershipStatus;
use CorvMC\SpaceManagement\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class StripeWebhookController extends CashierWebhookController
{
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
            } elseif ($checkoutType === 'ticket_order') {
                return $this->handleTicketOrderCheckout($session, $metadata);
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
        $sessionId = $session['id'];
        $reservationId = $metadata['reservation_id'] ?? null;

        $success = \CorvMC\SpaceManagement\Actions\Reservations\ProcessReservationCheckout::run(
            $reservationId,
            $sessionId
        );

        if (! $success && ! $reservationId) {
            // Only return error if processing failed due to something other than missing ID
            Log::warning('Stripe webhook: Failed to process reservation checkout', [
                'session_id' => $sessionId,
                'reservation_id' => $reservationId,
            ]);
        }

        return $this->successMethod();
    }

    /**
     * Handle subscription checkout completion webhook.
     *
     * Note: checkout.session.completed fires before Cashier syncs the subscription to our DB.
     * We can't rely on DB state here, so we process it the same as the redirect.
     * The customer.subscription.created webhook will handle final reconciliation.
     */
    private function handleSubscriptionCheckout(array $session, array $metadata): SymfonyResponse
    {
        $sessionId = $session['id'];
        $userId = $metadata['user_id'] ?? null;

        $success = \CorvMC\Finance\Actions\Subscriptions\ProcessSubscriptionCheckout::run(
            $userId,
            $sessionId,
            $metadata
        );

        if (! $success && $userId) {
            Log::warning('Stripe webhook: Failed to process subscription checkout', [
                'session_id' => $sessionId,
                'user_id' => $userId,
            ]);
        }

        return $this->successMethod();
    }

    /**
     * Handle ticket order checkout completion webhook.
     */
    private function handleTicketOrderCheckout(array $session, array $metadata): SymfonyResponse
    {
        $sessionId = $session['id'];
        $orderId = $metadata['ticket_order_id'] ?? null;

        if (! $orderId) {
            Log::warning('Stripe webhook: No ticket order ID in checkout metadata', [
                'session_id' => $sessionId,
            ]);

            return $this->successMethod();
        }

        $success = \CorvMC\Events\Actions\Tickets\CompleteTicketOrder::run(
            (int) $orderId,
            $sessionId
        );

        if (! $success) {
            Log::warning('Stripe webhook: Failed to process ticket order checkout', [
                'session_id' => $sessionId,
                'order_id' => $orderId,
            ]);
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

            if (! $reservationId) {
                Log::warning('Stripe webhook: No reservation ID in payment failure metadata');

                return $this->successMethod();
            }

            $reservation = Reservation::find($reservationId);

            if (! $reservation) {
                Log::error('Stripe webhook: Reservation not found for payment failure', ['reservation_id' => $reservationId]);

                return $this->successMethod();
            }

            // Handle failed payment
            \CorvMC\SpaceManagement\Actions\Reservations\HandleFailedPayment::run($reservation);

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
                UpdateUserMembershipStatus::run($user);

                // Allocate monthly credits now that subscription exists and role is assigned
                \CorvMC\Finance\Actions\MemberBenefits\AllocateUserMonthlyCredits::run($user);

                Log::info('Stripe webhook: Updated membership status and allocated credits after subscription creation', [
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
                UpdateUserMembershipStatus::run($user);

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
                UpdateUserMembershipStatus::run($user);

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
                UpdateUserMembershipStatus::run($user);
                // Allocate monthly credits if they're a sustaining member
                // This is idempotent - won't double-allocate in same month
                if ($user->hasRole('sustaining member')) {
                    \CorvMC\Finance\Actions\MemberBenefits\AllocateUserMonthlyCredits::run($user);

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
