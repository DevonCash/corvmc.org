<?php

namespace App\Filament\Staff\Resources\Events\Pages;

use App\Filament\Staff\Resources\Events\Actions\CancelEventAction;
use App\Filament\Staff\Resources\Events\Actions\PublishEventAction;
use App\Filament\Staff\Resources\Events\Actions\RescheduleEventAction;
use App\Filament\Staff\Resources\Events\EventResource;
use CorvMC\Events\Actions\DeleteEvent as DeleteEventAction;
use CorvMC\Events\Actions\UpdateEvent as UpdateEventAction;
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
            PublishEventAction::make(),
            RescheduleEventAction::make(),
            CancelEventAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
