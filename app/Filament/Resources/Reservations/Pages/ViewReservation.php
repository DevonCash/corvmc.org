<?php

namespace App\Filament\Resources\Reservations\Pages;

use App\Filament\Resources\Reservations\ReservationResource;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\Reservations\Actions\PayStripeAction;

class ViewReservation extends ViewRecord
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        $reservation = $this->getRecord();
        $user = Auth::user();
        $actions = [];

        // Only show edit for rehearsal reservations (production reservations are edited elsewhere)
        if ($reservation instanceof \App\Models\RehearsalReservation) {
            $actions[] = EditAction::make();
        }

        // Add payment action if reservation requires payment and user owns it or has permission
        if ($reservation instanceof \App\Models\RehearsalReservation &&
            $reservation->cost->isPositive() &&
            !$reservation->isPaid() &&
            ($reservation->reservable_id === $user->id || $user->can('manage reservations'))) {

            $actions[] = PayStripeAction::make();
        }

        return $actions;
    }
}
