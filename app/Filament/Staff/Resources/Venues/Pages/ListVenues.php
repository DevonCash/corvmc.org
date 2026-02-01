<?php

namespace App\Filament\Staff\Resources\Venues\Pages;

use App\Filament\Staff\Resources\Events\EventResource;
use App\Filament\Staff\Resources\Venues\VenueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVenues extends ListRecords
{
    protected static string $resource = VenueResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            EventResource::getUrl('index') => 'Events',
            VenueResource::getUrl('index') => 'Venues',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
