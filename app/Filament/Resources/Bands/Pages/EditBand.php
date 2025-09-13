<?php

namespace App\Filament\Resources\Bands\Pages;

use App\Filament\Resources\Bands\BandResource;
use App\Filament\Traits\HasCrudService;
use App\Models\Band;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBand extends EditRecord
{
    use HasCrudService;

    protected static string $resource = BandResource::class;
    protected static ?string $crudService = 'BandService';

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
