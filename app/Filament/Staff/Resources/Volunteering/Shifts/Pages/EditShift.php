<?php

namespace App\Filament\Staff\Resources\Volunteering\Shifts\Pages;

use App\Filament\Staff\Resources\Volunteering\Shifts\ShiftResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShift extends EditRecord
{
    protected static string $resource = ShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
