<?php

namespace App\Filament\Resources\Equipment\EquipmentDamageReports\Pages;

use App\Filament\Resources\Equipment\EquipmentDamageReports\EquipmentDamageReportResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditEquipmentDamageReport extends EditRecord
{
    protected static string $resource = EquipmentDamageReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
