<?php

namespace App\Filament\Resources\RecurringReservations\Pages;

use App\Filament\Resources\RecurringReservations\RecurringReservationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRecurringReservation extends EditRecord
{
    protected static string $resource = RecurringReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
