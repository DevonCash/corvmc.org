<?php

namespace App\Filament\Resources\Productions\Pages;

use App\Actions\Productions\DeleteProduction as DeleteProductionAction;
use App\Actions\Productions\UpdateProduction as UpdateProductionAction;
use App\Filament\Resources\Productions\ProductionResource;
use App\Models\Production;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProduction extends EditRecord
{
    protected static string $resource = ProductionResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return UpdateProductionAction::run($record, $data);
    }

    protected function handleRecordDeletion(Model $record): void
    {
        DeleteProductionAction::run($record);
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
