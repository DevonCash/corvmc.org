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
        $actions = [];

        // Add confirm action for scheduled reservations
        $actions[] = ConfirmReservation::filamentAction();
        $actions[] = CreateCheckoutSession::filamentAction();
        $actions[] = CancelReservation::filamentAction();

        return $actions;
    }
}
