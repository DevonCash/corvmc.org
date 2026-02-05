<?php

namespace App\Filament\Staff\Resources\RecurringReservations\Pages;

use App\Filament\Staff\Resources\RecurringReservations\RecurringReservationResource;
use App\Filament\Staff\Resources\SpaceManagement\SpaceManagementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecurringReservations extends ListRecords
{
    protected static string $resource = RecurringReservationResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            SpaceManagementResource::getUrl() => 'Space Management',
            'Recurring Reservations',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
