<?php

namespace App\Filament\Staff\Resources\SpaceManagement\Pages;

use App\Filament\Staff\Resources\RecurringReservations\RecurringReservationResource;
use App\Filament\Staff\Resources\SpaceClosures\SpaceClosureResource;
use App\Filament\Staff\Resources\SpaceManagement\Actions\LockSetupAction;
use App\Filament\Staff\Resources\SpaceManagement\SpaceManagementResource;
use App\Filament\Staff\Resources\SpaceManagement\Widgets\SpaceStatsWidget;
use App\Filament\Staff\Resources\SpaceManagement\Widgets\UpcomingClosuresWidget;
use CorvMC\SpaceManagement\Models\Reservation;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSpaceUsage extends ListRecords
{
    protected static string $resource = SpaceManagementResource::class;

    protected static ?string $title = 'Space Management';

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('space_closures')
                ->label('Space Closures')
                ->icon('tabler-calendar-off')
                ->color('gray')
                ->url(SpaceClosureResource::getUrl('index')),

            Action::make('recurring_reservations')
                ->label('Recurring Rehearsals')
                ->icon('tabler-calendar-repeat')
                ->color('gray')
                ->url(RecurringReservationResource::getUrl('index')),

            LockSetupAction::make(),


        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UpcomingClosuresWidget::class,
            SpaceStatsWidget::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'upcoming' => Tab::make('Upcoming')
                ->icon('tabler-calendar-clock')
                ->badge(function () {
                    return Reservation::where('reserved_until', '>', now())
                        ->where('status', '!=', 'cancelled')
                        ->count();
                })
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->where('reserved_until', '>', now())
                    ->where('status', '!=', 'cancelled')
                    ->orderBy('reserved_at', 'asc')),

            'all' => Tab::make('All')
                ->icon('tabler-calendar')
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->orderBy('reserved_at', 'desc')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'upcoming';
    }

}
