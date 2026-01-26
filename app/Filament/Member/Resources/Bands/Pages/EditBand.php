<?php

namespace App\Filament\Member\Resources\Bands\Pages;

use CorvMC\Membership\Actions\Bands\DeleteBand as DeleteBandAction;
use CorvMC\Membership\Actions\Bands\UpdateBand as UpdateBandAction;
use App\Filament\Member\Resources\Bands\BandResource;
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
            \App\Filament\Member\Resources\Bands\RelationManagers\MembersRelationManager::class,
        ];
    }
}
