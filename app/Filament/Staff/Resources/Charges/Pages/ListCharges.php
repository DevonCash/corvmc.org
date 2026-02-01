<?php

namespace App\Filament\Staff\Resources\Charges\Pages;

use App\Filament\Staff\Resources\Charges\ChargeResource;
use Filament\Resources\Pages\ListRecords;

class ListCharges extends ListRecords
{
    protected static string $resource = ChargeResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
