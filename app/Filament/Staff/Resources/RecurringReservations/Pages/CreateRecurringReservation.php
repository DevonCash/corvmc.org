<?php

namespace App\Filament\Staff\Resources\RecurringReservations\Pages;

use App\Filament\Staff\Resources\RecurringReservations\RecurringReservationResource;
use App\Filament\Staff\Resources\SpaceManagement\SpaceManagementResource;
use App\Models\User;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Filament\Resources\Pages\CreateRecord;

class CreateRecurringReservation extends CreateRecord
{
    protected static string $resource = RecurringReservationResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            SpaceManagementResource::getUrl() => 'Space Management',
            RecurringReservationResource::getUrl() => 'Recurring Reservations',
            'New',
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $forUser = User::find($data['user_id']);

        $this->authorize('scheduleRecurring', [RehearsalReservation::class, $forUser]);
        // Build RRULE from form inputs
        $data['recurrence_rule'] = \CorvMC\SpaceManagement\Actions\RecurringReservations\BuildRRule::run($data);

        // Set recurable_type to RehearsalReservation for this resource
        $data['recurable_type'] = 'rehearsal_reservation';

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
