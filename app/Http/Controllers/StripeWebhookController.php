<?php

namespace App\Http\Controllers;

use CorvMC\SpaceManagement\Models\Reservation;
use App\Models\User;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Facades\PaymentService;
use CorvMC\Finance\Facades\SubscriptionService;
use CorvMC\Finance\Facades\MemberBenefitService;
use CorvMC\Finance\Models\Transaction;
use CorvMC\Events\Facades\TicketService;
use Illuminate\Support\Facades\DB;
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

            if ($checkoutType === 'order') {
                return $this->handleOrderCheckout($payload, $session, $metadata);
            } elseif ($checkoutType === 'practice_space_reservation') {
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
     * Handle Order checkout completion via the new finance system.
     *
     * Uses stripe_webhook_events for idempotency. Resolves the Transaction
     * from session metadata and calls Finance::settle().
     */
    private function handleOrderCheckout(array $payload, array $session, array $metadata): SymfonyResponse
    {
        $eventId = $payload['id'] ?? null;

        // Idempotency: skip if we've already processed this webhook event
        if ($eventId && $this->isWebhookEventProcessed($eventId)) {
            Log::info('Stripe webhook: Skipping duplicate order checkout event', [
                'event_id' => $eventId,
            ]);

            return $this->successMethod();
        }

        $transactionId = $metadata['transaction_id'] ?? null;
        $paymentIntentId = $session['payment_intent'] ?? null;

        if (! $transactionId) {
            Log::warning('Stripe webhook: No transaction_id in order checkout metadata', [
                'session_id' => $session['id'],
            ]);

            return $this->successMethod();
        }

        $transaction = Transaction::find($transactionId);

        if (! $transaction) {
            Log::error('Stripe webhook: Transaction not found for order checkout', [
                'transaction_id' => $transactionId,
                'session_id' => $session['id'],
            ]);

            return $this->successMethod();
        }

        // Record webhook event before processing — settle() is idempotent,
        // so a duplicate arrival after a crash is harmless.
        if ($eventId) {
            $this->recordWebhookEvent($eventId, 'checkout.session.completed');
        }

        try {
            Finance::settle($transaction, $paymentIntentId);

            Log::info('Stripe webhook: Settled order transaction', [
                'transaction_id' => $transaction->id,
                'order_id' => $transaction->order_id,
                'session_id' => $session['id'],
            ]);
        } catch (\RuntimeException $e) {
            // Transaction already settled (e.g. by redirect handler) — not an error
            Log::info('Stripe webhook: Transaction already settled', [
                'transaction_id' => $transaction->id,
                'message' => $e->getMessage(),
            ]);
        }

        return $this->successMethod();
    }

    /**
     * Check if a Stripe webhook event has already been processed.
     */
    private function isWebhookEventProcessed(string $eventId): bool
    {
        return DB::table('stripe_webhook_events')
            ->where('event_id', $eventId)
            ->exists();
    }

    /**
     * Record a Stripe webhook event as processed.
     */
    private function recordWebhookEvent(string $eventId, string $eventType): void
    {
        DB::table('stripe_webhook_events')->insertOrIgnore([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Handle reservation checkout completion.
     */
    private function handleReservationCheckout(array $session, array $metadata): SymfonyResponse
    {
        $sessionId = $session['id'];
        $paymentIntentId = $session['payment_intent'] ?? null;
        $reservationId = $metadata['reservation_id'] ?? null;

        $success = PaymentService::processCheckout(
            $reservationId,
            $sessionId,
            $paymentIntentId
        );

        if (! $success && ! $reservationId) {
            // Only return error if processing failed due to something other than missing ID
            Log::warning('Stripe webhook: Failed to process reservation checkout', [
                'session_id' => $sessionId,
                'payment_intent_id' => $paymentIntentId,
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

        $success = SubscriptionService::processCheckout(
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

        $success = TicketService::completeOrder(
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
     * Handle charge.refunded webhook — settle refund Transactions.
     *
     * Looks up the refund Transaction via the Stripe refund ID stored
     * in metadata by Finance::refund(). Transitions it to Cleared.
     */
    public function handleChargeRefunded(array $payload): SymfonyResponse
    {
        $eventId = $payload['id'] ?? null;

        if ($eventId && $this->isWebhookEventProcessed($eventId)) {
            return $this->successMethod();
        }

        if ($eventId) {
            $this->recordWebhookEvent($eventId, 'charge.refunded');
        }

        try {
            $charge = $payload['data']['object'];
            $paymentIntentId = $charge['payment_intent'] ?? null;

            if (! $paymentIntentId) {
                Log::warning('Stripe webhook: No payment_intent on charge.refunded event');

                return $this->successMethod();
            }

            // Find all Pending refund Transactions whose original payment
            // used this payment_intent_id
            $refundTransactions = Transaction::where('type', 'refund')
                ->whereState('status', \CorvMC\Finance\States\TransactionState\Pending::class)
                ->where('currency', 'stripe')
                ->get()
                ->filter(function ($txn) use ($paymentIntentId) {
                    // Walk back to the original payment Transaction
                    $originalId = $txn->metadata['original_transaction_id'] ?? null;
                    if (! $originalId) {
                        return false;
                    }
                    $original = Transaction::find($originalId);

                    return $original && ($original->metadata['payment_intent_id'] ?? null) === $paymentIntentId;
                });

            foreach ($refundTransactions as $transaction) {
                try {
                    Finance::settle($transaction);

                    Log::info('Stripe webhook: Settled refund transaction', [
                        'transaction_id' => $transaction->id,
                        'payment_intent_id' => $paymentIntentId,
                    ]);
                } catch (\RuntimeException $e) {
                    Log::info('Stripe webhook: Refund transaction already settled', [
                        'transaction_id' => $transaction->id,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Stripe webhook: Error processing charge.refunded', [
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
            PaymentService::handleFailedPayment($reservation);

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
                SubscriptionService::updateUserMembershipStatus($user);

                // Allocate monthly credits now that subscription exists and role is assigned
                MemberBenefitService::allocateUserMonthlyCredits($user);

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
                SubscriptionService::updateUserMembershipStatus($user);

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
                SubscriptionService::updateUserMembershipStatus($user);

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
                SubscriptionService::updateUserMembershipStatus($user);
                // Allocate monthly credits if they're a sustaining member
                // This is idempotent - won't double-allocate in same month
                if ($user->hasRole('sustaining member')) {
                    MemberBenefitService::allocateUserMonthlyCredits($user);

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
