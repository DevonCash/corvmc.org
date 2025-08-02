<?php

namespace App\Filament\Resources\Productions\Pages;

use App\Filament\Resources\Productions\ProductionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduction extends CreateRecord
{
    protected static string $resource = ProductionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Convert at_cmc to location.is_external
        if (isset($data['at_cmc'])) {
            $data['location']['is_external'] = ! $data['at_cmc'];
            unset($data['at_cmc']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $notaflof = $this->data['notaflof'] ?? false;
        $this->record->setNotaflof($notaflof);
    }
}
