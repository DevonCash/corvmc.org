<?php

namespace App\Filament\Resources\Reservations\Pages;

use App\Filament\Resources\Reservations\ReservationResource;
use App\Filament\Resources\Reservations\Widgets\ReservationStatsOverview;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListReservations extends ListRecords
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ReservationStatsOverview::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'upcoming' => Tab::make('Upcoming')
                ->icon('heroicon-o-clock')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('reserved_at', '>', now())),

            'all' => Tab::make('All')
                ->icon('heroicon-o-queue-list'),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'upcoming';
    }
}
