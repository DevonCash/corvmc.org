<?php

namespace App\Filament\Resources\Reservations\Actions;

use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class PayStripeAction
{
    public static function make(): Action
    {
        return Action::make('pay_stripe')
            ->label('Pay Online')
            ->icon('tabler-credit-card')
            ->color('success')
            ->visible(fn(Reservation $record) =>
                $record->cost > 0 &&
                $record->isUnpaid() &&
                ($record->user_id === Auth::id() || Auth::user()->can('manage reservations'))
            )
            ->action(function (Reservation $record) {
                // Ensure user owns the reservation or has permission
                if ($record->user_id !== Auth::id() && !Auth::user()->can('manage reservations')) {
                    Notification::make()
                        ->title('Access Denied')
                        ->body('You do not have permission to pay for this reservation.')
                        ->danger()
                        ->send();
                    return;
                }

                // Check if reservation needs payment
                if ($record->cost <= 0) {
                    Notification::make()
                        ->title('Payment Not Required')
                        ->body('This reservation does not require payment.')
                        ->warning()
                        ->send();
                    return;
                }

                if ($record->isPaid()) {
                    Notification::make()
                        ->title('Already Paid')
                        ->body('This reservation has already been paid.')
                        ->info()
                        ->send();
                    return;
                }

                try {
                    $reservationService = app(ReservationService::class);
                    $session = $reservationService->createCheckoutSession($record);
                    
                    // Redirect to Stripe checkout
                    return redirect($session->url);
                    
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Payment Error')
                        ->body('Unable to create payment session: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}