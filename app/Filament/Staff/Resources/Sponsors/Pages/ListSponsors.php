<?php

namespace App\Filament\Staff\Resources\Sponsors\Pages;

use App\Filament\Staff\Resources\Sponsors\SponsorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSponsors extends ListRecords
{
    protected static string $resource = SponsorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
