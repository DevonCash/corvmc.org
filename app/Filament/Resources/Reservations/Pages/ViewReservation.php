<?php

namespace App\Filament\Resources\Reservations\Pages;

use App\Filament\Resources\Reservations\ReservationResource;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewReservation extends ViewRecord
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            EditAction::make(),
        ];

        // Add payment action if reservation requires payment and user owns it or has permission
        $reservation = $this->getRecord();
        $user = Auth::user();

        if ($reservation->cost > 0 &&
            !$reservation->isPaid() &&
            ($reservation->user_id === $user->id || $user->can('manage reservations'))) {

            $actions[] = Action::make('payNow')
                ->label('Pay Now')
                ->icon('tabler-credit-card')
                ->color('success')
                ->url(route('reservations.payment.checkout', $reservation))
                ->openUrlInNewTab(false);
        }

        return $actions;
    }
}
