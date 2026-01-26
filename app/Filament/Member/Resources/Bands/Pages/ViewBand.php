<?php

namespace App\Filament\Member\Resources\Bands\Pages;

use CorvMC\Membership\Actions\Bands\AddBandMember;
use App\Filament\Shared\Actions\ReportContentAction;
use App\Filament\Member\Resources\Bands\BandResource;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * @property \App\Models\Band $record
 */
class ViewBand extends Page
{
    use InteractsWithRecord;

    protected static string $resource = BandResource::class;

    protected string $view = 'filament.resources.band-profiles.pages.view-band-profile';

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return $this->record->name;
    }

    public function getSubheading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $genres = $this->record->tagsWithType('genre')->pluck('name');
        $location = $this->record->hometown ? " • {$this->record->hometown}" : '';

        if ($genres->count() > 0) {
            $genreText = $genres->take(3)->join(', ');

            return $genreText.$location;
        }

        return 'Band'.$location;
    }

    public function getBreadCrumbs(): array
    {
        return [
            route('filament.member.resources.bands.index') => 'Band Profiles',
            route('filament.member.resources.bands.view', ['record' => $this->record]) => $this->record->name,
        ];
    }

    public function getHeaderActions(): array
    {
        return [
            AddBandMember::filamentAction()
                ->record($this->record),
            EditAction::make()
                ->visible(fn () => User::me()?->can('update', $this->record)),
            ReportContentAction::make()
                ->visible(fn () => Auth::user()->id !== $this->record->owner_id), // Don't show report button to owner
        ];
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        // Check if user can view this profile
        if (! User::me()?->can('view', $this->record)) {
            abort(403, 'You do not have permission to view this band profile.');
        }
    }

    public function getHeader(): ?View
    {
        return null;
    }

    protected function getViewData(): array
    {
        return [
            'record' => $this->record,
        ];
    }
}
