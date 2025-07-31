<?php

namespace App\Filament\Resources\BandProfiles\Pages;

use App\Filament\Resources\BandProfiles\BandProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBandProfiles extends ListRecords
{
    protected static string $resource = BandProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
