<?php

namespace App\Filament\Resources\RecurringReservations\Pages;

use App\Filament\Resources\RecurringReservations\RecurringReservationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecurringReservations extends ListRecords
{
    protected static string $resource = RecurringReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
