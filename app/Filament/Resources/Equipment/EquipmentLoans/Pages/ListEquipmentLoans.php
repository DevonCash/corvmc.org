<?php

namespace App\Filament\Resources\Equipment\EquipmentLoans\Pages;

use App\Filament\Resources\Equipment\EquipmentLoans\EquipmentLoanResource;
use App\Filament\Resources\Equipment\EquipmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEquipmentLoans extends ListRecords
{
    protected static string $resource = EquipmentLoanResource::class;

    protected static ?string $parentResource = EquipmentResource::class;

    protected function getTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        return parent::getTableQuery()->with(['borrower', 'equipment']);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
