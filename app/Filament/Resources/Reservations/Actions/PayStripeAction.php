<?php

namespace App\Filament\Resources\Reservations\Actions;

use App\Models\Reservation;
use App\Models\User;
use Filament\Actions\Action;

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
                ($record->user_id === User::me()->id || User::me()->can('manage reservations'))
            )
            ->url(fn(Reservation $record) => route('reservations.payment.checkout', $record))
            ->openUrlInNewTab(false);
    }
}