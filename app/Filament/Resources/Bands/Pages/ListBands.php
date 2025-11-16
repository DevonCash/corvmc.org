<?php

namespace App\Filament\Resources\Bands\Pages;

use App\Actions\Bands\CreateBand;
use App\Filament\Resources\Bands\BandResource;
use App\Filament\Resources\Bands\Widgets\PendingBandInvitationsWidget;
use App\Models\Band;
use App\Models\User;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBands extends ListRecords
{
    protected static string $resource = BandResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            PendingBandInvitationsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateBand::filamentAction(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all_bands' => Tab::make('All Bands'),
            'my_bands' => Tab::make('My Bands')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where(function (Builder $query) {
                        $query->where('owner_id', User::me()->id)
                            ->orWhereHas('members', function (Builder $query) {
                                $query->where('user_id', User::me()->id)
                                    ->where('status', 'active');
                            });
                    })
                ),
        ];
    }

    protected function getPendingInvitationsCount(): int
    {
        return Band::whereHas('members', function ($query) {
            $query->where('user_id', User::me()->id)
                ->where('status', 'invited');
        })->count();
    }
}
