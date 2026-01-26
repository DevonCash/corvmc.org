<?php

namespace App\Filament\Member\Resources\Reservations\Pages;

use CorvMC\SpaceManagement\Actions\Reservations\CancelReservation;
use CorvMC\SpaceManagement\Actions\Reservations\ConfirmReservation;
use CorvMC\SpaceManagement\Actions\Reservations\CreateCheckoutSession;
use App\Filament\Member\Resources\Reservations\ReservationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

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
