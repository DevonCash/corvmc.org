<?php

namespace App\Filament\Staff\Resources\SpaceManagement\Pages;

use App\Filament\Member\Resources\Reservations\Schemas\ReservationInfolist;
use App\Filament\Staff\Resources\SpaceManagement\SpaceManagementResource;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Activitylog\Models\Activity;

class ViewSpaceUsage extends ViewRecord
{
    protected static string $resource = SpaceManagementResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            ...ReservationInfolist::configure(Schema::make($this))->getComponents(),
            Section::make('Activity History')
                ->icon('tabler-activity')
                ->collapsed()
                ->schema([
                    ViewEntry::make('activity_log')
                        ->hiddenLabel()
                        ->view('filament.staff.components.reservation-activity-log')
                        ->state(fn () => Activity::forSubject($this->record)
                            ->with('causer')
                            ->latest()
                            ->get()),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
