<?php

namespace App\Filament\Actions\Payments;

use CorvMC\Finance\Facades\PaymentService;
use CorvMC\SpaceManagement\Models\Reservation;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

class MarkReservationAsPaidAction
{
    public static function bulkAction(): Action
    {
        return Action::make('markAsPaid')
            ->label('Mark as Paid')
            ->icon('tabler-check')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Mark Reservations as Paid')
            ->modalDescription('Are you sure you want to mark these reservations as paid? This will record them as paid in cash or other offline method.')
            ->action(function (Collection $records) {
                $count = 0;
                foreach ($records as $reservation) {
                    if ($reservation->charge && !$reservation->charge->isPaid()) {
                        PaymentService::markReservationAsPaid($reservation);
                        $count++;
                    }
                }
                
                Notification::make()
                    ->title("Marked {$count} reservation(s) as paid")
                    ->success()
                    ->send();
            });
    }
}