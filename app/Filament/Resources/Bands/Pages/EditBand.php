<?php

namespace App\Filament\Resources\Bands\Pages;

use App\Filament\Resources\Bands\BandResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBand extends EditRecord
{
    protected static string $resource = BandResource::class;

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
