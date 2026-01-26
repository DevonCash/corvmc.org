<?php

namespace App\Filament\Staff\Resources\SpaceManagement\Pages;

use CorvMC\SpaceManagement\Actions\Reservations\CreateReservation;
use App\Filament\Member\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Staff\Resources\SpaceManagement\SpaceManagementResource;
use App\Models\User;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;

class CreateSpaceUsage extends CreateRecord
{
    protected static string $resource = SpaceManagementResource::class;

    public function form(Schema $schema): Schema
    {
        return ReservationForm::configure($schema);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // The form already sets reserved_at and reserved_until via updateDateTimes
        // Just ensure we have the user_id
        if (! isset($data['user_id'])) {
            $data['user_id'] = auth()->id();
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $user = User::find($data['user_id']);

        // reserved_at and reserved_until are already Carbon instances from ReservationForm
        $reservedAt = $data['reserved_at'];
        $reservedUntil = $data['reserved_until'];

        // Create the reservation using the action
        $reservation = CreateReservation::run(
            $user,
            $reservedAt,
            $reservedUntil,
            [
                'status' => $data['status'] ?? 'confirmed',
                'notes' => $data['notes'] ?? null,
                'is_recurring' => $data['is_recurring'] ?? false,
            ]
        );

        return $reservation;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
