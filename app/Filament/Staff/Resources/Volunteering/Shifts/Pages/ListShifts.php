<?php

namespace App\Filament\Staff\Resources\Volunteering\Shifts\Pages;

use App\Filament\Staff\Resources\Volunteering\Shifts\ShiftResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShifts extends ListRecords
{
    protected static string $resource = ShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
