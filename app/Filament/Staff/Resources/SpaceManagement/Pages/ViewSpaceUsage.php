<?php

namespace App\Filament\Staff\Resources\SpaceManagement\Pages;

use App\Filament\Member\Resources\Reservations\Schemas\ReservationInfolist;
use App\Filament\Staff\Resources\SpaceManagement\SpaceManagementResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewSpaceUsage extends ViewRecord
{
    protected static string $resource = SpaceManagementResource::class;

    public function infolist(Schema $schema): Schema
    {
        return ReservationInfolist::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
