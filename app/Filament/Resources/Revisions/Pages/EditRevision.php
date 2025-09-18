<?php

namespace App\Filament\Resources\Revisions\Pages;

use App\Filament\Resources\Revisions\RevisionResource;
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
