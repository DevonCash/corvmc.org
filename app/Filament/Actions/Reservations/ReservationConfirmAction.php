<?php

namespace App\Filament\Actions\Reservations;

use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Services\ReservationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ReservationConfirmAction
{
    public static function make(): Action
    {
        return Action::make('confirmReservation')
            ->label('Confirm')
            ->color('success')
            ->icon('tabler-check')
            ->authorize(function (RehearsalReservation $record) {
                return auth()->user()->can('confirm', $record);
            })
            ->visible(function (RehearsalReservation $record) {
                return $record->canConfirm();
            })
            ->requiresConfirmation()
            ->modalHeading('Confirm Reservation')
            ->modalDescription(fn(RehearsalReservation $record) => "Are you sure you want to confirm this reservation for {$record->reserved_at->format('M j, g:i A')}?")
            ->modalSubmitActionLabel('Confirm Reservation')
            ->action(function (RehearsalReservation $record) {
                try {
                    $record->confirm();

                    Notification::make()
                        ->title('Reservation confirmed')
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Failed to confirm reservation')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
