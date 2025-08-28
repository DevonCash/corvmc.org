<?php

namespace App\Filament\Resources\Bands\Pages;

use App\Filament\Resources\Bands\BandResource;
use App\Models\Band;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBands extends ListRecords
{
    protected static string $resource = BandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('band_invites')
                ->label('Band Invites')
                ->icon('heroicon-o-envelope')
                ->color('warning')
                ->badge(fn () => $this->getPendingInvitationsCount())
                ->badgeColor('danger')
                ->url(route('filament.member.pages.band-invitations'))
                ->visible(fn () => $this->getPendingInvitationsCount() > 0),
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all_bands' => Tab::make('All Bands'),
            'my_bands' => Tab::make('My Bands')
                ->modifyQueryUsing(fn (Builder $query) =>
                    $query->where(function (Builder $query) {
                        $query->where('owner_id', auth()->id())
                              ->orWhereHas('members', function (Builder $query) {
                                  $query->where('user_id', auth()->id())
                                        ->where('status', 'active');
                              });
                    })
                ),
        ];
    }

    protected function getPendingInvitationsCount(): int
    {
        return Band::whereHas('members', function ($query) {
            $query->where('user_id', auth()->id())
                ->where('status', 'invited');
        })->count();
    }
}
