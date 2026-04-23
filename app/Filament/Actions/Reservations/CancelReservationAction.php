<?php

namespace App\Filament\Actions\Reservations;

use CorvMC\SpaceManagement\Facades\ReservationService;
use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\States\ReservationState;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

class CancelReservationAction
{
    public static function make(): Action
    {
        return Action::make('cancelReservation')
            ->label('Cancel')
            ->modalSubmitActionLabel('Cancel Reservation')
            ->modalCancelActionLabel('Keep Reservation')
            ->modalDescription('Are you sure you want to cancel this reservation? This action cannot be undone. Free hours used will be refunded if applicable.')
            ->icon('tabler-calendar-x')
            ->color('danger')
            ->visible(
                fn(?Reservation $record) => $record?->status->isActive() && $record->reserved_until > now()
            )
            ->authorize('cancel')
            ->requiresConfirmation()
            ->action(function (?Reservation $record) {
                $record->status->transitionTo(ReservationState\Cancelled::class);
                Notification::make()
                    ->title('Reservation cancelled')
                    ->success()
                    ->send();
            });
    }

    public static function bulkAction(): Action
    {
        return Action::make('bulkCancelReservations')
            ->label('Cancel Reservations')
            ->icon('tabler-calendar-x')
            ->color('danger')
            ->requiresConfirmation()
            ->modalSubmitActionLabel('Cancel All')
            ->modalCancelActionLabel('Keep Reservations')
            ->modalDescription('Are you sure you want to cancel these reservations? This action cannot be undone.')
            ->action(function (Collection $records) {
                $count = 0;
                foreach ($records as $reservation) {
                    if ($reservation->status->canTransitionTo(ReservationState\Cancelled::class)) {
                        $reservation->state->transitionTo(ReservationState\Cancelled::class);
                        $count++;
                    }
                }

                Notification::make()
                    ->title("Cancelled {$count} reservation(s)")
                    ->success()
                    ->send();
            });
    }
}
