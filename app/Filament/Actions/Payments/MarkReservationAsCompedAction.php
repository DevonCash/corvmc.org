<?php

namespace App\Filament\Actions\Payments;

use CorvMC\Finance\Facades\PaymentService;
use CorvMC\SpaceManagement\Models\Reservation;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

class MarkReservationAsCompedAction
{
    public static function bulkAction(): Action
    {
        return Action::make('markAsComped')
            ->label('Mark as Comped')
            ->icon('tabler-gift')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('Comp Reservations')
            ->modalDescription('Are you sure you want to comp these reservations? This will mark them as free with no charge.')
            ->action(function (Collection $records) {
                $count = 0;
                foreach ($records as $reservation) {
                    if ($reservation->charge && !$reservation->charge->isComped()) {
                        PaymentService::markReservationAsComped($reservation);
                        $count++;
                    }
                }
                
                Notification::make()
                    ->title("Comped {$count} reservation(s)")
                    ->success()
                    ->send();
            });
    }
}