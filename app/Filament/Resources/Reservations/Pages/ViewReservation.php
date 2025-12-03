<?php

namespace App\Filament\Resources\Reservations\Pages;

use App\Actions\Reservations\CancelReservation;
use App\Actions\Reservations\ConfirmReservation;
use App\Actions\Reservations\CreateCheckoutSession;
use App\Filament\Resources\Reservations\ReservationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewReservation extends ViewRecord
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        $reservation = $this->getRecord();
        $user = Auth::user();
        $actions = [];

        // Add confirm action for scheduled reservations
        if (
            $reservation instanceof \App\Models\RehearsalReservation &&
            $reservation->canBeConfirmed() &&
            ($reservation->reservable_id === $user->id || $user->can('manage reservations'))
        ) {
            $actions[] = ConfirmReservation::filamentAction();
        }

        // Add payment action if reservation requires payment and user owns it or has permission
        if (
            $reservation instanceof \App\Models\RehearsalReservation &&
            $reservation->cost->isPositive() &&
            ! $reservation->isPaid() &&
            ($reservation->reservable_id === $user->id || $user->can('manage reservations'))
        ) {

            $actions[] = CreateCheckoutSession::filamentAction();
        }

        // Add cancel action for active reservations
        if (
            $reservation instanceof \App\Models\RehearsalReservation &&
            $reservation->status->isActive() &&
            ($reservation->reservable_id === $user->id || $user->can('manage reservations'))
        ) {
            $actions[] = CancelReservation::filamentAction();
        }

        return $actions;
    }
}
