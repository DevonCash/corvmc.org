<?php

namespace App\Filament\Resources\Reservations\Actions;

use App\Actions\Reservations\CreateCheckoutSession;
use App\Models\Reservation;
use App\Models\User;
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
            ->visible(
                fn(Reservation $record) =>
                $record->cost->isPositive() && $record->isUnpaid() &&
                    ($record->reservable_id === Auth::id() || Auth::user()->can('manage reservations'))
            )
            ->action(function (Reservation $record) {
                $session = CreateCheckoutSession::run($record);
                // Redirect to Stripe checkout
                return redirect($session->url);
            });
    }
}
