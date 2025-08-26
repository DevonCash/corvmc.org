<?php

namespace App\Filament\Resources\BandProfiles\Pages;

use App\Filament\Resources\BandProfiles\BandProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBandProfile extends EditRecord
{
    protected static string $resource = BandProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }


    public function getRelationManagers(): array
    {
        return [
            \App\Filament\Resources\BandProfiles\RelationManagers\MembersRelationManager::class,
        ];
    }
}
