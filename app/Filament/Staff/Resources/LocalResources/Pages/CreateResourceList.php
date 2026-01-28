<?php

namespace App\Filament\Staff\Resources\LocalResources\Pages;

use App\Filament\Staff\Resources\LocalResources\ResourceListResource;
use Filament\Resources\Pages\CreateRecord;

class CreateResourceList extends CreateRecord
{
    protected static string $resource = ResourceListResource::class;
}
