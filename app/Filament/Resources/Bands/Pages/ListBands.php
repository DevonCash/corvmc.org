<?php

namespace App\Filament\Resources\Bands\Pages;

use App\Actions\Bands\CreateBand as CreateBandAction;
use App\Actions\Bands\FindClaimableBand;
use App\Filament\Resources\Bands\BandResource;
use App\Models\Band;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListBands extends ListRecords
{
    protected static string $resource = BandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('band_invites')
                ->label('Band Invites')
                ->icon('tabler-mail')
                ->color('warning')
                ->badge(fn () => $this->getPendingInvitationsCount())
                ->badgeColor('danger')
                ->url(route('filament.member.pages.band-invitations'))
                ->visible(fn () => $this->getPendingInvitationsCount() > 0),
            Action::make('create')
                ->label('Create Band')
                ->icon('heroicon-o-plus')
                ->modalHeading('Create New Band')
                ->modalWidth('md')
                ->form([
                    TextInput::make('name')
                        ->label('Band Name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Enter your band name')
                        ->autofocus(),
                ])
                ->action(function (array $data) {
                    // Check for claimable bands with the same name first
                    $claimableBand = FindClaimableBand::run($data['name']);

                    if ($claimableBand && Auth::user()->can('claim', $claimableBand)) {
                        // Redirect to claiming workflow instead of creating duplicate
                        session()->flash('claimable_band', [
                            'id' => $claimableBand->id,
                            'name' => $claimableBand->name,
                            'data' => $data
                        ]);

                        $this->redirect(BandResource::getUrl('claim'));
                        return;
                    }

                    // Create the band
                    $band = CreateBandAction::run($data);

                    // Redirect to edit page
                    $this->redirect(BandResource::getUrl('edit', ['record' => $band]));
                }),
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
