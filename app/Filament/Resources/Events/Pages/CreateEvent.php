<?php

namespace App\Filament\Resources\Events\Pages;

use App\Actions\Events\CreateEvent as CreateEventAction;
use App\Filament\Resources\Events\EventResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->combineDateTimeFields($data);
    }

    protected function handleRecordCreation(array $data): Model
    {
        return CreateEventAction::run($data);
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
