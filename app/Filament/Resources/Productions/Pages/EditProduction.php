<?php

namespace App\Filament\Resources\Productions\Pages;

use App\Filament\Resources\Productions\ProductionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProduction extends EditRecord
{
    protected static string $resource = ProductionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Convert at_cmc to location.is_external
        if (isset($data['at_cmc'])) {
            $data['location']['is_external'] = ! $data['at_cmc'];
            unset($data['at_cmc']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $notaflof = $this->data['notaflof'] ?? false;
        $this->record->setNotaflof($notaflof);
    }
}
