<?php

namespace App\Filament\Resources\SpaceManagement\Pages;

use App\Actions\Reservations\UpdateReservation;
use App\Filament\Resources\SpaceManagement\SpaceManagementResource;
use App\Filament\Resources\Reservations\Schemas\ReservationEditForm;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class EditSpaceUsage extends EditRecord
{
    protected static string $resource = SpaceManagementResource::class;

    public function form(Schema $schema): Schema
    {
        return ReservationEditForm::configure($schema);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Convert datetime to date and time components for the form
        // The datetime values are already Carbon instances from the model cast
        if (isset($data['reserved_at'])) {
            $reservedAt = $data['reserved_at'] instanceof Carbon ? $data['reserved_at'] : Carbon::parse($data['reserved_at']);
            $data['reservation_date'] = $reservedAt->toDateString();
            $data['start_time'] = $reservedAt->format('H:i');
        }
        if (isset($data['reserved_until'])) {
            $reservedUntil = $data['reserved_until'] instanceof Carbon ? $data['reserved_until'] : Carbon::parse($data['reserved_until']);
            $data['end_time'] = $reservedUntil->format('H:i');
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Combine date and time fields in the app's timezone
        $startTime = Carbon::parse($data['reservation_date'] . ' ' . $data['start_time'], config('app.timezone'));
        $endTime = Carbon::parse($data['reservation_date'] . ' ' . $data['end_time'], config('app.timezone'));

        $options = [
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? $record->status,
            'payment_status' => $data['payment_status'] ?? $record->payment_status,
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
