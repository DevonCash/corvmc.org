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
            'today' => Tab::make('Today')
                ->icon('tabler-calendar-today')
                ->badge(function () {
                    return $this->getTodayCount();
                })
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->whereDate('reserved_at', today())
                    ->where('status', '!=', 'cancelled')
                    ->orderBy('reserved_at', 'asc')),

            'upcoming' => Tab::make('Upcoming')
                ->icon('tabler-calendar-clock')
                ->badge(function () {
                    return Reservation::where('reserved_at', '>', now())
                        ->where('status', '!=', 'cancelled')
                        ->count();
                })
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->where('reserved_at', '>', now())
                    ->where('status', '!=', 'cancelled')
                    ->orderBy('reserved_at', 'asc')),

            'needs_attention' => Tab::make('Needs Attention')
                ->icon('tabler-alert-circle')
                ->badge(function () {
                    return Reservation::where(function ($q) {
                            $q->where('status', 'pending')
                            ->where('reserved_at', '>', now())
                                ->orWhere(function ($q) {
                                    $q->where('payment_status', 'unpaid')
                                        ->where('cost', '>', 0);
                                });
                        })->count();
                })
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->where(function ($q) {
                        $q->where('status', 'pending')
                            ->orWhere(function ($q) {
                                $q->where('payment_status', 'unpaid')
                                    ->where('cost', '>', 0);
                            });
                    })
                    ->where('status', '!=', 'cancelled')
                    ->orderBy('reserved_at', 'asc')),

            'all' => Tab::make('All')
                ->icon('tabler-calendar')
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->orderBy('reserved_at', 'desc')),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'today';
    }

    protected function getTodayCount(): int
    {
        // Count all reservation types (rehearsal and production) using space today
        return Reservation::whereDate('reserved_at', today())
            ->where('status', '!=', 'cancelled')
            ->count();
    }
}
