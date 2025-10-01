<?php

namespace App\Filament\Resources\RecurringReservations\Pages;

use App\Filament\Resources\RecurringReservations\RecurringReservationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecurringReservation extends CreateRecord
{
    protected static string $resource = RecurringReservationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Build RRULE from form inputs
        $service = app(\App\Services\RecurringReservationService::class);
        $data['recurrence_rule'] = $service->buildRRule($data);

        // Remove temporary form fields
        unset($data['frequency'], $data['interval'], $data['by_day']);

        return $data;
    }

    protected function afterCreate(): void
    {
        // Generate initial instances after creating series
        $service = app(\App\Services\RecurringReservationService::class);
        $service->generateInstances($this->record);
    }
}
