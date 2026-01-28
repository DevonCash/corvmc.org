<?php

namespace App\Filament\Staff\Resources\EquipmentDamageReports\Pages;

use App\Filament\Staff\Resources\EquipmentDamageReports\EquipmentDamageReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEquipmentDamageReports extends ListRecords
{
    protected static string $resource = EquipmentDamageReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
