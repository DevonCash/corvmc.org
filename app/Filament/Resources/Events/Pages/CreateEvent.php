<?php

namespace App\Filament\Resources\Events\Pages;

use CorvMC\Events\Actions\CreateEvent as CreateEventAction;
use App\Filament\Resources\Events\EventResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return CreateEventAction::run($data);
    }
}
