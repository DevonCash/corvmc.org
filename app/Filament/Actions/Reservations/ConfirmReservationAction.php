<?php

namespace App\Filament\Actions\Reservations;

use CorvMC\SpaceManagement\Facades\ReservationService;
use CorvMC\SpaceManagement\Models\Reservation;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ConfirmReservationAction
{
    public static function make(): Action
    {
        return Action::make('confirmReservation')
            ->label('Confirm')
            ->icon('tabler-check')
            ->color('success')
            ->visible(fn (Reservation $record) => $record->status->canConfirm())
            ->authorize('confirm')
            ->requiresConfirmation()
            ->modalHeading('Confirm Reservation')
            ->modalDescription('Are you sure you want to confirm this reservation? This will finalize your booking.')
            ->modalSubmitActionLabel('Confirm Reservation')
            ->action(function (Reservation $record) {
                ReservationService::confirm($record);
                Notification::make()
                    ->title('Reservation confirmed')
                    ->success()
                    ->send();
            });
    }
}