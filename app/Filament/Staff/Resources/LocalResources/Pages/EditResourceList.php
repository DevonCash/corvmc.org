<?php

namespace App\Filament\Staff\Resources\LocalResources\Pages;

use App\Filament\Staff\Resources\LocalResources\ResourceListResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditResourceList extends EditRecord
{
    protected static string $resource = ResourceListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
