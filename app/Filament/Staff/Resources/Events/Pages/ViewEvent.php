<?php

namespace App\Filament\Staff\Resources\Events\Pages;

use App\Filament\Shared\Actions\ReportContentAction;
use App\Filament\Staff\Resources\Events\EventResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEvent extends ViewRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ReportContentAction::make(),
        ];
    }
}
