<?php

namespace App\Filament\Staff\Resources\Volunteering\Positions\Pages;

use App\Filament\Staff\Resources\Volunteering\Positions\PositionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPosition extends EditRecord
{
    protected static string $resource = PositionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
