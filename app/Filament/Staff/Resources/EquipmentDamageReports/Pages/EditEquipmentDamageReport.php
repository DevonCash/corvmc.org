<?php

namespace App\Filament\Staff\Resources\EquipmentDamageReports\Pages;

use App\Filament\Staff\Resources\EquipmentDamageReports\EquipmentDamageReportResource;
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
