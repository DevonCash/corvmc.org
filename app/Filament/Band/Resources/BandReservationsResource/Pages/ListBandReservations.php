<?php

namespace App\Filament\Band\Resources\BandReservationsResource\Pages;

use App\Filament\Band\Resources\BandReservationsResource;
use App\Models\Band;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListBandReservations extends ListRecords
{
    protected static string $resource = BandReservationsResource::class;

    protected static ?string $title = 'Band Reservations';

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getTableQuery(): Builder
    {
        /** @var Band $band */
        $band = Filament::getTenant();

        return parent::getTableQuery()
            ->where('reservable_type', Band::class)
            ->where('reservable_id', $band->id);
    }

    public function getTabs(): array
    {
        return [
            'upcoming' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('reserved_at', '>', now())),
            'all' => Tab::make(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Reserve Space')
                ->icon('tabler-calendar-plus'),
        ];
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Reserve Space')
                    ->icon('tabler-calendar-plus'),
            ]);
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'upcoming';
    }
}
