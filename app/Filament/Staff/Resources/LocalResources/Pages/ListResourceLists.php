<?php

namespace App\Filament\Staff\Resources\LocalResources\Pages;

use App\Filament\Staff\Resources\LocalResources\ResourceListResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListResourceLists extends ListRecords
{
    protected static string $resource = ResourceListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
