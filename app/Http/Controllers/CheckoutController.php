<?php

namespace App\Http\Controllers;

use App\Models\User;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Facades\PaymentService;
use CorvMC\Finance\Facades\SubscriptionService;
use CorvMC\Finance\Models\Transaction;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;

class CheckoutController extends Controller
{
    /**
     * Handle successful checkout for any type (subscription, reservation, etc.).
     * Processes payment directly for immediate confirmation, with webhooks as backup.
     */
    public function success(Request $request)
    {
        $sessionId = $request->get('session_id');
        $userId = $request->get('user_id');

        if (! $sessionId || ! $userId) {
            Notification::make()
                ->title('Invalid subscription session')
                ->body('Missing required parameters for subscription confirmation.')
                ->danger()
                ->send();

            return redirect(filament()->getUrl());
        }

        $user = User::find($userId);
        if (! $user) {
            Notification::make()
                ->title('User not found')
                ->body('Unable to find the user for this subscription.')
                ->danger()
                ->send();

            return redirect(filament()->getUrl());
        }

        // Security: Verify the logged-in user matches the checkout user
        if ($user->id !== auth()->id()) {
            Log::warning('Unauthorized checkout success attempt', [
                'authenticated_user' => auth()->id(),
                'checkout_user' => $user->id,
                'session_id' => $sessionId,
            ]);

            Notification::make()
                ->title('Unauthorized')
                ->body('You are not authorized to view this checkout.')
                ->danger()
                ->send();

            return redirect(filament()->getUrl());
        }

        // Initialize variables for use outside try block
        $checkoutType = 'unknown';
        $metadata = [];

        try {
            // Retrieve the checkout session to verify it was successful and determine type
            $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId);
            $metadata = $session->metadata ? $session->metadata->toArray() : [];
            $checkoutType = $metadata['type'] ?? 'unknown';

            // Additional security: Verify Stripe customer matches the user
            if ($session->customer && $user->stripe_id && $session->customer !== $user->stripe_id) {
                Log::warning('Stripe customer mismatch in checkout', [
                    'user_id' => $user->id,
                    'user_stripe_id' => $user->stripe_id,
                    'session_customer' => $session->customer,
                    'session_id' => $sessionId,
                ]);

                Notification::make()
                    ->title('Payment verification failed')
                    ->body('Unable to verify payment. Please contact support.')
                    ->danger()
                    ->send();

                return redirect(filament()->getUrl());
            }

            if ($session->payment_status === 'paid') {
                // Process payment immediately for better UX
                if ($checkoutType === 'order') {
                    $this->settleOrderTransaction($metadata, $session);
                } elseif ($checkoutType === 'practice_space_reservation') {
                    PaymentService::processCheckout(
                        $metadata['reservation_id'] ?? null,
                        $sessionId
                    );
                } elseif ($checkoutType === 'sliding_scale_membership') {
                    SubscriptionService::processCheckout(
                        $metadata['user_id'] ?? null,
                        $sessionId,
                        $metadata
                    );
                }

                // Success! Show confirmation
                $this->showSuccessNotification($checkoutType, $metadata);

                Log::info('Checkout success page viewed', [
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'checkout_type' => $checkoutType,
                    'payment_status' => $session->payment_status,
                    'reservation_id' => $metadata['reservation_id'] ?? null,
                ]);
            } else {
                // Payment not completed yet
                $this->showPendingNotification($checkoutType);

                Log::info('Checkout with pending payment', [
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'checkout_type' => $checkoutType,
                    'payment_status' => $session->payment_status,
                ]);
            }
        } catch (\Exception $e) {
            // If we can't retrieve the session, still show success since user was redirected here
            Notification::make()
                ->title('Payment Processing')
                ->body('Your payment is being processed. You will receive confirmation shortly.')
                ->info()
                ->send();

            Log::warning('Could not retrieve checkout session on success page', [
                'user_id' => $userId,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
        }

        // Redirect based on checkout type
        return $this->getSuccessRedirect($checkoutType, $metadata, $user);
    }

    /**
     * Handle cancelled checkout for any type.
     *
     * For order checkouts, immediately transitions the Stripe Transaction
     * to Failed so the Order stays Pending for staff/retry without waiting
     * for the 24h session expiry webhook.
     */
    public function cancel(Request $request)
    {
        $userId = $request->get('user_id');
        $checkoutType = $request->get('type', 'checkout');

        // Fail the Stripe Transaction immediately for order checkouts
        if ($checkoutType === 'order') {
            $this->failOrderTransaction($request);
        }

        $this->showCancelNotification($checkoutType);

        Log::info('Checkout cancelled', [
            'user_id' => $userId,
            'checkout_type' => $checkoutType,
        ]);

        // Redirect based on checkout type and user
        if ($userId) {
            $user = User::find($userId);
            if ($user) {
                return $this->getCancelRedirect($checkoutType, $user);
            }
        }

        return redirect(filament()->getUrl());
    }

    /**
     * Fail the Stripe Transaction when the customer cancels checkout.
     *
     * Verifies the authenticated user owns the Transaction before transitioning.
     */
    private function failOrderTransaction(Request $request): void
    {
        $transactionId = $request->get('transaction_id');

        if (! $transactionId) {
            return;
        }

        $transaction = Transaction::find($transactionId);

        if (! $transaction) {
            return;
        }

        // Security: only the owning user can fail their own Transaction
        if ($transaction->user_id !== auth()->id()) {
            Log::warning('Checkout cancel: user mismatch on transaction', [
                'transaction_id' => $transactionId,
                'transaction_user' => $transaction->user_id,
                'authenticated_user' => auth()->id(),
            ]);

            return;
        }

        Finance::fail($transaction);

        Log::info('Checkout cancel: Marked transaction as failed', [
            'transaction_id' => $transaction->id,
            'order_id' => $transaction->order_id,
        ]);
    }

    /**
     * Show appropriate success notification based on checkout type.
     */
    private function showSuccessNotification(string $checkoutType, array $metadata): void
    {
        match ($checkoutType) {
            'order' => Notification::make()
                ->title('Payment Successful!')
                ->body('Your payment has been processed and your order is confirmed.')
                ->success()
                ->send(),

            'sliding_scale_membership' => Notification::make()
                ->title('Subscription Created Successfully!')
                ->body('You are now a sustaining member. Thank you for your support! Your subscription will be activated shortly.')
                ->success()
                ->send(),

            'practice_space_reservation' => Notification::make()
                ->title('Reservation Payment Successful!')
                ->body('Your practice space reservation has been confirmed. You will receive a confirmation email shortly.')
                ->success()
                ->send(),

            default => Notification::make()
                ->title('Payment Successful!')
                ->body('Your payment has been processed successfully. You will receive confirmation shortly.')
                ->success()
                ->send(),
        };
    }

    /**
     * Show appropriate pending notification based on checkout type.
     */
    private function showPendingNotification(string $checkoutType): void
    {
        match ($checkoutType) {
            'order' => Notification::make()
                ->title('Payment Processing')
                ->body('Your payment is being processed. You will receive confirmation shortly.')
                ->warning()
                ->send(),

            'sliding_scale_membership' => Notification::make()
                ->title('Subscription Processing')
                ->body('Your subscription is being processed. You will receive confirmation shortly.')
                ->warning()
                ->send(),

            'practice_space_reservation' => Notification::make()
                ->title('Reservation Processing')
                ->body('Your reservation payment is being processed. You will receive confirmation shortly.')
                ->warning()
                ->send(),

            default => Notification::make()
                ->title('Payment Processing')
                ->body('Your payment is being processed. You will receive confirmation shortly.')
                ->warning()
                ->send(),
        };
    }

    /**
     * Show appropriate cancel notification based on checkout type.
     */
    private function showCancelNotification(string $checkoutType): void
    {
        match ($checkoutType) {
            'order' => Notification::make()
                ->title('Checkout Cancelled')
                ->body('Your checkout was cancelled. Your order is still pending — you can try again anytime.')
                ->warning()
                ->send(),

            'sliding_scale_membership' => Notification::make()
                ->title('Subscription Cancelled')
                ->body('Your subscription checkout was cancelled. You can try again anytime.')
                ->warning()
                ->send(),

            'practice_space_reservation' => Notification::make()
                ->title('Reservation Cancelled')
                ->body('Your reservation checkout was cancelled. You can try again anytime.')
                ->warning()
                ->send(),

            default => Notification::make()
                ->title('Checkout Cancelled')
                ->body('Your checkout was cancelled. You can try again anytime.')
                ->warning()
                ->send(),
        };
    }

    /**
     * Get appropriate success redirect based on checkout type.
     */
    private function getSuccessRedirect(string $checkoutType, array $metadata, User $user)
    {
        return match ($checkoutType) {
            'order' => $this->getOrderRedirect($metadata),
            'practice_space_reservation' => $this->getReservationRedirect($metadata),
            'sliding_scale_membership' => redirect()->route('filament.member.pages.membership'),
            default => redirect()->route('filament.member.pages.profile'),
        };
    }

    /**
     * Get appropriate cancel redirect based on checkout type.
     */
    private function getCancelRedirect(string $checkoutType, User $user)
    {
        return match ($checkoutType) {
            'order' => redirect()->route('filament.member.pages.profile'),
            'practice_space_reservation' => redirect()->route('filament.member.resources.reservations.index'),
            'sliding_scale_membership' => redirect()->route('filament.member.pages.membership'),
            default => redirect()->route('filament.member.pages.profile'),
        };
    }

    /**
     * Get reservation-specific redirect from metadata.
     */
    private function getReservationRedirect(array $metadata)
    {
        // Always redirect to the index page - users can view/edit from there
        return redirect()->route('filament.member.resources.reservations.index');
    }

    /**
     * Settle an Order's Transaction from the checkout redirect.
     *
     * Gracefully handles the case where the webhook already settled it.
     */
    private function settleOrderTransaction(array $metadata, object $session): void
    {
        $transactionId = $metadata['transaction_id'] ?? null;

        if (! $transactionId) {
            return;
        }

        $transaction = Transaction::find($transactionId);

        if (! $transaction) {
            Log::warning('Checkout success: Transaction not found for order', [
                'transaction_id' => $transactionId,
            ]);

            return;
        }

        $paymentIntentId = $session->payment_intent ?? null;

        try {
            Finance::settle($transaction, $paymentIntentId);
        } catch (\RuntimeException $e) {
            // Already settled by webhook — not an error
            Log::info('Checkout success: Transaction already settled', [
                'transaction_id' => $transaction->id,
            ]);
        }
    }

    /**
     * Redirect after successful Order checkout, based on the Order's primary product type.
     */
    private function getOrderRedirect(array $metadata)
    {
        $orderId = $metadata['order_id'] ?? null;

        if ($orderId) {
            $order = \CorvMC\Finance\Models\Order::find($orderId);

            if ($order) {
                $primaryType = $order->lineItems()
                    ->where('amount', '>=', 0)
                    ->value('product_type');

                return match ($primaryType) {
                    'rehearsal_time' => redirect()->route('filament.member.resources.reservations.index'),
                    'ticket' => redirect()->route('filament.member.resources.events.index'),
                    default => redirect()->route('filament.member.pages.profile'),
                };
            }
        }

        return redirect()->route('filament.member.pages.profile');
    }

}
