<?php

namespace App\Filament\Staff\Resources\KioskDevices\Pages;

use App\Filament\Staff\Resources\KioskDevices\KioskDeviceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateKioskDevice extends CreateRecord
{
    protected static string $resource = KioskDeviceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
