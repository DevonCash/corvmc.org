<?php

namespace App\Filament\Resources\SpaceManagement\Pages;

use App\Filament\Resources\SpaceManagement\SpaceManagementResource;
use App\Filament\Resources\Reservations\Schemas\ReservationInfolist;
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
            // Redirect to appropriate panel to edit
            EditAction::make()
                ->url(fn ($record) => route('filament.member.resources.reservations.edit', $record))
                ->openUrlInNewTab(false),
        ];
    }
}
