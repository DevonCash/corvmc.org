<?php

namespace App\Filament\Member\Resources\Reservations\Pages;

use App\Filament\Actions\Reservations\CancelReservationAction;
use App\Filament\Actions\Reservations\PayWithCashAction;
use App\Filament\Actions\Reservations\PayWithStripeAction;
use App\Filament\Member\Resources\Reservations\ReservationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewReservation extends ViewRecord
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PayWithStripeAction::make(),
            PayWithCashAction::make(),
            CancelReservationAction::make(),
        ];
    }
}
