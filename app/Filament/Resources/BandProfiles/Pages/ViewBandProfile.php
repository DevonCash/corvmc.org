<?php

namespace App\Filament\Resources\BandProfiles\Pages;

use App\Filament\Resources\BandProfiles\BandProfileResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ViewBandProfile extends Page
{
    use InteractsWithRecord;

    protected static string $resource = BandProfileResource::class;

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
            return $genreText . $location;
        }
        
        return 'Band' . $location;
    }

    public function getBreadCrumbs(): array
    {
        return [
            route('filament.member.resources.band-profiles.index') => 'Band Profiles',
            route('filament.member.resources.band-profiles.view', ['record' => $this->record->id]) => $this->record->name,
        ];
    }

    public function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn () => 
                    auth()->user()->can('update', $this->record)
                ),
        ];
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        
        // Check if user can view this profile
        if (!auth()->user()->can('view', $this->record)) {
            abort(403, 'You do not have permission to view this band profile.');
        }
    }

    protected function getViewData(): array
    {
        return [
            'record' => $this->record,
        ];
    }
}