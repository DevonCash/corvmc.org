<?php

namespace App\Filament\Resources\Reservations\Actions;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

class BulkCancelAction
{
    public static function make(): Action
    {
        return Action::make('bulk_cancel')
            ->visible(fn() => Auth::user()->can('manage reservations'))
            ->requiresConfirmation()
            ->action(function (Collection $records) {
                foreach ($records as $record) {
                    \App\Facades\ReservationService::cancelReservation($record);
                }

                Notification::make()
                    ->title('Reservations cancelled')
                    ->body("{$records->count()} reservations marked as cancelled")
                    ->success()
                    ->send();
            });
    }
}