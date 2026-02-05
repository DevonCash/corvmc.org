<?php

namespace App\Filament\Staff\Resources\RecurringReservations\Pages;

use App\Filament\Staff\Resources\RecurringReservations\RecurringReservationResource;
use App\Filament\Staff\Resources\SpaceManagement\SpaceManagementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRecurringReservation extends EditRecord
{
    protected static string $resource = RecurringReservationResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            SpaceManagementResource::getUrl() => 'Space Management',
            RecurringReservationResource::getUrl() => 'Recurring Reservations',
            'Edit',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
