<?php

namespace App\Filament\Member\Resources\Reservations\Pages;

use App\Filament\Actions\Reservations\CancelReservationAction;
use App\Filament\Actions\Reservations\ConfirmReservationAction;
use App\Filament\Actions\Reservations\CreateCheckoutSessionAction;
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
        $actions[] = ConfirmReservationAction::make();
        $actions[] = CreateCheckoutSessionAction::make();
        $actions[] = CancelReservationAction::make();

        return $actions;
    }
}
