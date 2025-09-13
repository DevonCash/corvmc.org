<?php

namespace App\Filament\Resources\Productions\Pages;

use App\Filament\Resources\Productions\ProductionResource;
use App\Filament\Traits\HasCrudService;
use App\Models\Production;
use Filament\Resources\Pages\CreateRecord;

class CreateProduction extends CreateRecord
{
    use HasCrudService;

    protected static string $resource = ProductionResource::class;
    protected static ?string $crudService = 'ProductionService';
}
