<?php

namespace App\Filament\Actions\Reservations;

use CorvMC\SpaceManagement\Facades\ReservationService;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;

class CreateCheckoutSessionAction
{
    public static function make(): Action
    {
        return Action::make('pay_stripe')
            ->label('Pay Online')
            ->icon('tabler-credit-card')
            ->color('success')
            ->visible(fn (Reservation $record) => $record instanceof RehearsalReservation &&
                $record->requiresPayment() && ($record->reservable_id === Auth::id() || User::me()->can('manage reservations')))
            ->action(function (Reservation $record) {
                $session = ReservationService::createCheckoutSession($record);

                return redirect($session->url);
            });
    }
}