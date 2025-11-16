<?php

namespace App\Filament\Resources\Events\Pages;

use App\Actions\Events\DeleteEvent as DeleteEventAction;
use App\Actions\Events\UpdateEvent as UpdateEventAction;
use App\Filament\Resources\Events\EventResource;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->combineDateTimeFields($data);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return UpdateEventAction::run($record, $data);
    }

    protected function handleRecordDeletion(Model $record): void
    {
        DeleteEventAction::run($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function combineDateTimeFields(array $data): array
    {
        $timezone = config('app.timezone');
        $date = $data['event_date'] ?? null;

        if ($date && isset($data['start_time_only'])) {
            $data['start_time'] = Carbon::parse("{$date} {$data['start_time_only']}", $timezone);
        }

        if ($date && isset($data['end_time_only'])) {
            $data['end_time'] = Carbon::parse("{$date} {$data['end_time_only']}", $timezone);
        }

        if ($date && isset($data['doors_time_only'])) {
            $data['doors_time'] = Carbon::parse("{$date} {$data['doors_time_only']}", $timezone);
        }

        unset($data['event_date'], $data['start_time_only'], $data['end_time_only'], $data['doors_time_only']);

        return $data;
    }
}
