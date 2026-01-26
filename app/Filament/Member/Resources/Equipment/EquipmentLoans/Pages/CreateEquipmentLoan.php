<?php

namespace App\Filament\Member\Resources\Equipment\EquipmentLoans\Pages;

use App\Filament\Member\Resources\Equipment\EquipmentLoans\EquipmentLoanResource;
use App\Filament\Member\Resources\Equipment\EquipmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEquipmentLoan extends CreateRecord
{
    protected static string $resource = EquipmentLoanResource::class;

    protected static ?string $parentResource = EquipmentResource::class;
}
