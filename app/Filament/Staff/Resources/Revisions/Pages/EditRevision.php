<?php

namespace App\Filament\Staff\Resources\Revisions\Pages;

use App\Filament\Staff\Resources\Revisions\RevisionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRevision extends EditRecord
{
    protected static string $resource = RevisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
