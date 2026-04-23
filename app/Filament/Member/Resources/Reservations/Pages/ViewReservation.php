<?php

namespace App\Filament\Member\Resources\Reservations\Pages;

use App\Filament\Actions\Reservations\CancelReservationAction;
use App\Filament\Actions\Reservations\PayWithCashAction;
use App\Filament\Actions\Reservations\PayWithStripeAction;
use App\Filament\Member\Resources\Reservations\ReservationResource;
use App\Filament\Staff\Resources\Orders\Actions\RetryPaymentAction;
use CorvMC\Finance\Facades\Finance;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewReservation extends ViewRecord
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        /** @var RehearsalReservation $record */
        $record = $this->record;
        $order = Finance::findActiveOrder($record);

        return [
            PayWithStripeAction::make(),
            PayWithCashAction::make(),

            // Retry operates on the Order, resolved from the reservation
            ...($order ? [RetryPaymentAction::make()->record($order)] : []),

            CancelReservationAction::make(),
        ];
    }
}
