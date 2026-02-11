<?php

namespace App\Filament\Staff\Resources\SitePages\Pages;

use App\Filament\Staff\Resources\SitePages\SitePageResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSitePage extends EditRecord
{
    protected static string $resource = SitePageResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
