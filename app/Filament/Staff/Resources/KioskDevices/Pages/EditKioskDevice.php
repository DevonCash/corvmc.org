<?php

namespace App\Filament\Staff\Resources\KioskDevices\Pages;

use App\Filament\Staff\Resources\KioskDevices\KioskDeviceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKioskDevice extends EditRecord
{
    protected static string $resource = KioskDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
