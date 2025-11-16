<?php

namespace App\Filament\Resources\Equipment\EquipmentLoans\Pages;

use App\Filament\Resources\Equipment\EquipmentLoans\EquipmentLoanResource;
use App\Filament\Resources\Equipment\EquipmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEquipmentLoan extends EditRecord
{
    protected static string $resource = EquipmentLoanResource::class;

    protected static ?string $title = 'Equipment Loan';

    protected static ?string $parentResource = EquipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
