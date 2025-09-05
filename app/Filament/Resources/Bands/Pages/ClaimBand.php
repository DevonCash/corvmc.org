<?php

namespace App\Filament\Resources\Bands\Pages;

use App\Filament\Resources\Bands\BandResource;
use App\Models\Band;
use App\Services\BandService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Session;

class ClaimBand extends Page
{
    protected static string $resource = BandResource::class;
    protected string $view = 'filament.resources.bands.pages.claim-band';

    public ?Band $claimableBand = null;
    public ?array $originalData = null;
    public ?array $similarBands = null;

    public function mount(): void
    {
        $claimableData = session('claimable_band');

        if (!$claimableData) {
            $this->redirect(static::getResource()::getUrl('create'));
            return;
        }

        $this->claimableBand = Band::find($claimableData['id']);
        $this->originalData = $claimableData['data'];

        if (!$this->claimableBand) {
            $this->redirect(static::getResource()::getUrl('create'));
            return;
        }

        // Get similar bands for reference
        $bandService = \BandService::getFacadeRoot();
        $this->similarBands = $bandService->getSimilarBandNames($this->claimableBand->name, 10)->toArray();
    }

    public function claimBandAction(): Action
    {
        return Action::make('claimBand')
            ->label('Claim This Band')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Claim Band Ownership')
            ->modalDescription(fn() => "Are you sure you want to claim ownership of \"{$this->claimableBand->name}\"? This will make you the owner and allow you to manage the band profile.")
            ->action(function () {
                $bandService = \BandService::getFacadeRoot();

                if ($bandService->claimBand($this->claimableBand, auth()->user())) {
                    // Update the band with any new data from the original form
                    $updateData = array_filter($this->originalData, function ($value, $key) {
                        return $key !== 'name' && $value !== null && $value !== '';
                    }, ARRAY_FILTER_USE_BOTH);

                    if (!empty($updateData)) {
                        $this->claimableBand->update($updateData);
                    }

                    Session::forget('claimable_band');

                    Notification::make()
                        ->title('Band Claimed Successfully!')
                        ->body("You are now the owner of \"{$this->claimableBand->name}\".")
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('edit', ['record' => $this->claimableBand]));
                } else {
                    Notification::make()
                        ->title('Unable to Claim Band')
                        ->body('There was an issue claiming this band. Please try again.')
                        ->danger()
                        ->send();
                }
            });
    }

    public function createNewBandAction(): Action
    {
        return Action::make('createNewBand')
            ->label('Create New Band Instead')
            ->icon('heroicon-o-plus-circle')
            ->color('gray')
            ->action(function () {
                Session::forget('claimable_band');
                $this->redirect(static::getResource()::getUrl('create'));
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->claimBandAction(),
            $this->createNewBandAction(),
        ];
    }

    public function getTitle(): string
    {
        return 'Claim Band: ' . ($this->claimableBand->name ?? 'Unknown Band');
    }

    public static function getNavigationLabel(): string
    {
        return 'Claim Band';
    }

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false; // Don't show in navigation
    }
}
