<?php

namespace App\Http\Controllers;

use App\Services\UserSubscriptionService;
use App\Models\User;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;

class CheckoutController extends Controller
{
    public function __construct(
        private UserSubscriptionService $subscriptionService
    ) {}

    /**
     * Handle successful checkout for any type (subscription, reservation, etc.).
     * Note: The actual processing is handled by Stripe webhooks.
     * This is just the user-facing success page with confirmation.
     */
    public function success(Request $request)
    {
        $sessionId = $request->get('session_id');
        $userId = $request->get('user_id');

        if (!$sessionId || !$userId) {
            Notification::make()
                ->title('Invalid subscription session')
                ->body('Missing required parameters for subscription confirmation.')
                ->danger()
                ->send();

            return redirect()->route('filament.member.resources.users.index');
        }

        $user = User::find($userId);
        if (!$user) {
            Notification::make()
                ->title('User not found')
                ->body('Unable to find the user for this subscription.')
                ->danger()
                ->send();

            return redirect()->route('filament.member.resources.users.index');
        }

        // Initialize variables for use outside try block
        $checkoutType = 'unknown';
        $metadata = [];
        
        try {
            // Retrieve the checkout session to verify it was successful and determine type
            $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId);
            $metadata = $session->metadata ? $session->metadata->toArray() : [];
            $checkoutType = $metadata['type'] ?? 'unknown';
            
            if ($session->payment_status === 'paid') {
                // Success! Webhooks will handle the actual processing
                $this->showSuccessNotification($checkoutType, $metadata);
                    
                Log::info('Checkout success page viewed', [
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'checkout_type' => $checkoutType,
                    'payment_status' => $session->payment_status,
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
     */
    public function cancel(Request $request)
    {
        $userId = $request->get('user_id');
        $checkoutType = $request->get('type', 'checkout');

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

        return redirect()->route('filament.member.resources.users.index');
    }

    /**
     * Show appropriate success notification based on checkout type.
     */
    private function showSuccessNotification(string $checkoutType, array $metadata): void
    {
        match ($checkoutType) {
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
            'practice_space_reservation' => $this->getReservationRedirect($metadata),
            'sliding_scale_membership' => redirect()->route('filament.member.resources.users.edit', ['record' => $user->id]),
            default => redirect()->route('filament.member.resources.users.edit', ['record' => $user->id]),
        };
    }

    /**
     * Get appropriate cancel redirect based on checkout type.
     */
    private function getCancelRedirect(string $checkoutType, User $user)
    {
        return match ($checkoutType) {
            'practice_space_reservation' => redirect()->route('filament.member.resources.reservations.index'),
            'sliding_scale_membership' => redirect()->route('filament.member.resources.users.edit', ['record' => $user->id]),
            default => redirect()->route('filament.member.resources.users.edit', ['record' => $user->id]),
        };
    }

    /**
     * Get reservation-specific redirect from metadata.
     */
    private function getReservationRedirect(array $metadata)
    {
        $reservationId = $metadata['reservation_id'] ?? null;
        
        if ($reservationId && $reservation = Reservation::find($reservationId)) {
            return redirect()->route('filament.member.resources.reservations.view', $reservation);
        }
        
        return redirect()->route('filament.member.resources.reservations.index');
    }
}