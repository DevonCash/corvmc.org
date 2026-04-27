<?php

namespace App\Filament\Staff\Resources\Volunteering\Positions\Pages;

use App\Filament\Staff\Resources\Volunteering\Positions\PositionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPositions extends ListRecords
{
    protected static string $resource = PositionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
