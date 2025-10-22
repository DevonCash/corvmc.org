<?php

namespace App\Filament\Resources\SpaceManagement\Pages;

use App\Filament\Resources\SpaceManagement\SpaceManagementResource;
use App\Filament\Resources\SpaceManagement\Widgets\SpaceUsageWidget;
use App\Filament\Resources\SpaceManagement\Widgets\SpaceStatsWidget;
use App\Models\Production;
use App\Models\Reservation;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSpaceUsage extends ListRecords
{
    protected static string $resource = SpaceManagementResource::class;

    protected static ?string $title = 'Space Management';

    protected function getHeaderWidgets(): array
    {
        return [
            SpaceStatsWidget::class,
            SpaceUsageWidget::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'upcoming' => Tab::make('Upcoming')
                ->icon('tabler-calendar-clock')
                ->badge(function () {
                    return Reservation::where('reserved_at', '>=', now()->startOfDay())
                        ->where('status', '!=', 'cancelled')
                        ->count();
                })
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->where('reserved_at', '>=', now()->startOfDay())
                    ->where('status', '!=', 'cancelled')
                    ->orderBy('reserved_at', 'asc')),

            'needs_attention' => Tab::make('Needs Attention')
                ->icon('tabler-alert-circle')
                ->badge(fn() => Reservation::needsAttention()->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->needsAttention()
                    ->orderBy('reserved_at', 'asc')),

            'all' => Tab::make('All')
                ->icon('tabler-calendar')
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->orderBy('reserved_at', 'desc')),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'upcoming';
    }
}
