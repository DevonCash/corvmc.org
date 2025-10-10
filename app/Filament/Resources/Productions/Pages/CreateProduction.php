<?php

namespace App\Filament\Resources\Productions\Pages;

use App\Actions\Productions\CreateProduction as CreateProductionAction;
use App\Filament\Resources\Productions\ProductionResource;
use App\Models\Production;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProduction extends CreateRecord
{
    protected static string $resource = ProductionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return CreateProductionAction::run($data);
    }
}
