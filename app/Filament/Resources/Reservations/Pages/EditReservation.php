<?php

namespace App\Filament\Resources\Reservations\Pages;

use App\Actions\Reservations\UpdateReservation;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Filament\Resources\Reservations\Schemas\ReservationEditForm;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

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
        if (isset($data['reserved_at'])) {
            $data['reservation_date'] = Carbon::parse($data['reserved_at'])->toDateString();
            $data['start_time'] = Carbon::parse($data['reserved_at'])->format('H:i');
        }
        if (isset($data['reserved_until'])) {
            $data['end_time'] = Carbon::parse($data['reserved_until'])->format('H:i');
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Combine date and time fields
        $startTime = Carbon::parse($data['reservation_date'] . ' ' . $data['start_time']);
        $endTime = Carbon::parse($data['reservation_date'] . ' ' . $data['end_time']);

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
