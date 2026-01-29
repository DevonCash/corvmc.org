<?php

namespace App\Filament\Staff\Resources\KioskDevices\Pages;

use App\Filament\Staff\Resources\KioskDevices\KioskDeviceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKioskDevices extends ListRecords
{
    protected static string $resource = KioskDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
