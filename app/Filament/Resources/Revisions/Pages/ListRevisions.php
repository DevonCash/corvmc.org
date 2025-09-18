<?php

namespace App\Filament\Resources\Revisions\Pages;

use App\Filament\Resources\Revisions\RevisionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRevisions extends ListRecords
{
    protected static string $resource = RevisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
