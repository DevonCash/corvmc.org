<?php

namespace App\Filament\Actions\Reservations;

use CorvMC\SpaceManagement\Facades\ReservationService;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use App\Models\User;
use CorvMC\Finance\Contracts\Chargeable;
use CorvMC\Finance\Facades\PaymentService;
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
            ->authorize('charge')
            ->action(function (Chargeable $record) {
                $session = PaymentService::createCheckoutSession($record);
                return redirect($session->url);
            });
    }
}
