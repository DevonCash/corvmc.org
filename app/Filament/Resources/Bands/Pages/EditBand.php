<?php

namespace App\Filament\Resources\Bands\Pages;

use App\Actions\Bands\DeleteBand as DeleteBandAction;
use App\Actions\Bands\UpdateBand as UpdateBandAction;
use App\Filament\Resources\Bands\BandResource;
use App\Models\Band;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditBand extends EditRecord
{
    protected static string $resource = BandResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return UpdateBandAction::run($record, $data);
    }

    protected function handleRecordDeletion(Model $record): void
    {
        DeleteBandAction::run($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function getRelationManagers(): array
    {
        return [
            \App\Filament\Resources\Bands\RelationManagers\MembersRelationManager::class,
        ];
    }
}
