<?php

namespace App\Filament\Resources\Reservations\Pages;

use CorvMC\SpaceManagement\Actions\Reservations\UpdateReservation;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Filament\Resources\Reservations\Schemas\ReservationEditForm;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * @property \CorvMC\SpaceManagement\Models\Reservation $record
 */
class EditReservation extends EditRecord
{
    protected static string $resource = ReservationResource::class;

    public function form(Schema $schema): Schema
    {
        return ReservationEditForm::configure($schema);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Convert datetime to date and time components for the form
        // Access the model directly to avoid timezone conversion from toArray()
        if (isset($data['reserved_at'])) {
            $reservedAt = $this->record->reserved_at;
            $data['reservation_date'] = $reservedAt->toDateString();
            $data['start_time'] = $reservedAt->format('H:i');
        }
        if (isset($data['reserved_until'])) {
            $reservedUntil = $this->record->reserved_until;
            $data['end_time'] = $reservedUntil->format('H:i');
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var \CorvMC\SpaceManagement\Models\Reservation $record */
        // Combine date and time fields in the app's timezone
        $startTime = Carbon::parse($data['reservation_date'].' '.$data['start_time'], config('app.timezone'));
        $endTime = Carbon::parse($data['reservation_date'].' '.$data['end_time'], config('app.timezone'));

        $options = [
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? $record->status,
        ];

        return UpdateReservation::run($record, $startTime, $endTime, $options);
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
