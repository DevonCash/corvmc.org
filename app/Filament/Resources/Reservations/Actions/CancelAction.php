<?php

namespace App\Filament\Resources\Reservations\Actions;

use App\Models\Reservation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class CancelAction
{
    public static function make(): Action
    {
        return Action::make('cancel')
            ->icon('tabler-calendar-x')
            ->color('danger')
            ->visible(fn(Reservation $record) =>
                ($record instanceof \App\Models\RehearsalReservation && $record->reservable_id === Auth::id()) ||
                Auth::user()->can('manage reservations'))
            ->requiresConfirmation()
            ->action(function (Reservation $record) {
                \App\Actions\Reservations\CancelReservation::run($record);

                Notification::make()
                    ->title('Reservation Cancelled')
                    ->body('The reservation has been cancelled.')
                    ->success()
                    ->send();
            });
    }
}