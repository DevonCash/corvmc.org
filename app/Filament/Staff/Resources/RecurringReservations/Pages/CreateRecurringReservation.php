<?php

namespace App\Filament\Staff\Resources\RecurringReservations\Pages;

use App\Filament\Staff\Resources\RecurringReservations\RecurringReservationResource;
use CorvMC\SpaceManagement\Models\Reservation;
use Filament\Resources\Pages\CreateRecord;

class CreateRecurringReservation extends CreateRecord
{
    protected static string $resource = RecurringReservationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Build RRULE from form inputs
        $data['recurrence_rule'] = \CorvMC\SpaceManagement\Actions\RecurringReservations\BuildRRule::run($data);

        // Set recurable_type to Reservation for this resource
        $data['recurable_type'] = Reservation::class;

        // Remove temporary form fields
        unset($data['frequency'], $data['interval'], $data['by_day']);

        return $data;
    }

    protected function afterCreate(): void
    {
        // Generate initial instances after creating series
        \CorvMC\Support\Actions\GenerateRecurringInstances::run($this->record);
    }
}
