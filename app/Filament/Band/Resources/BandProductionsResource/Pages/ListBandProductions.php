<?php

namespace App\Filament\Band\Resources\BandProductionsResource\Pages;

use App\Filament\Band\Resources\BandProductionsResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBandProductions extends ListRecords
{
    protected static string $resource = BandProductionsResource::class;

    protected static ?string $title = 'Band Events';

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'upcoming' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('start_datetime', '>', now())),
            'past' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('start_datetime', '<=', now())),
            'all' => Tab::make(),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'upcoming';
    }
}
