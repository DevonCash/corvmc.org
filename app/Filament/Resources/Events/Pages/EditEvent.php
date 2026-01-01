<?php

namespace App\Filament\Resources\Events\Pages;

use App\Actions\Events\DeleteEvent as DeleteEventAction;
use App\Actions\Events\UpdateEvent as UpdateEventAction;
use App\Filament\Resources\Events\EventResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

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
}
