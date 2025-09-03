<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Filament\Notifications\Notification;

class ReservationPaymentController extends Controller
{
    public function __construct(
        private ReservationService $reservationService
    ) {}

    /**
     * Create Stripe checkout session and redirect to Stripe.
     */
    public function checkout(Reservation $reservation): RedirectResponse
    {
        // Ensure user owns the reservation or has permission
        if ($reservation->user_id !== auth()->id() && !auth()->user()->can('manage reservations')) {
            abort(403);
        }

        // Check if reservation needs payment
        if ($reservation->cost <= 0) {
            Notification::make()
                ->warning()
                ->title('Payment Not Required')
                ->body('This reservation does not require payment.')
                ->send();
                
            return redirect()->route('filament.member.resources.reservations.view', $reservation);
        }

        if ($reservation->isPaid()) {
            Notification::make()
                ->info()
                ->title('Already Paid')
                ->body('This reservation has already been paid.')
                ->send();
                
            return redirect()->route('filament.member.resources.reservations.view', $reservation);
        }

        try {
            $session = $this->reservationService->createCheckoutSession($reservation);
            
            return redirect($session->url);
            
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Payment Error')
                ->body('Unable to create payment session: ' . $e->getMessage())
                ->send();
                
            return redirect()->back();
        }
    }

    /**
     * Handle successful payment from Stripe.
     */
    public function success(Request $request, Reservation $reservation): RedirectResponse
    {
        $sessionId = $request->get('session_id');
        
        if (!$sessionId) {
            Notification::make()
                ->danger()
                ->title('Payment Error')
                ->body('No payment session found.')
                ->send();
                
            return redirect()->route('filament.member.resources.reservations.view', $reservation);
        }

        try {
            $transaction = $this->reservationService->handleSuccessfulPayment($reservation, $sessionId);
            
            Notification::make()
                ->success()
                ->title('Payment Successful')
                ->body(sprintf(
                    'Your payment of $%.2f has been processed successfully. Your reservation is now confirmed!',
                    $transaction->amount
                ))
                ->send();
                
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Payment Processing Error')
                ->body('Payment may have succeeded but there was an error updating your reservation: ' . $e->getMessage())
                ->send();
        }

        return redirect()->route('filament.member.resources.reservations.view', $reservation);
    }

    /**
     * Handle cancelled or failed payment from Stripe.
     */
    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        $sessionId = $request->get('session_id');
        
        $this->reservationService->handleFailedPayment($reservation, $sessionId);
        
        Notification::make()
            ->warning()
            ->title('Payment Cancelled')
            ->body('Your payment was cancelled. Your reservation is still pending payment.')
            ->send();

        return redirect()->route('filament.member.resources.reservations.view', $reservation);
    }
}
