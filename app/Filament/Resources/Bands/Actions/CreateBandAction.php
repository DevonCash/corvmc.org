<?php

namespace App\Filament\Resources\Bands\Actions;

use App\Actions\Bands\CreateBand as CreateBandAction;
use App\Actions\Bands\FindClaimableBand;
use App\Filament\Resources\Bands\BandResource;
use App\Models\Band;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;

class CreateBandAction
{
    public static function make(): Action
    {
        return Action::make('create')
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
            ->action(function (array $data, $livewire) {
                // Check for claimable bands with the same name first
                $claimableBand = FindClaimableBand::run($data['name']);

                if ($claimableBand && Auth::user()->can('claim', $claimableBand)) {
                    // Redirect to claiming workflow instead of creating duplicate
                    session()->flash('claimable_band', [
                        'id' => $claimableBand->id,
                        'name' => $claimableBand->name,
                        'data' => $data
                    ]);

                    $livewire->redirect(BandResource::getUrl('claim'));
                    return;
                }

                // Create the band
                $band = CreateBandAction::run($data);

                // Redirect to edit page
                $livewire->redirect(BandResource::getUrl('edit', ['record' => $band]));
            })
            ->visible(fn (): bool => Auth::user()?->can('create bands') ?? false);
    }
}
