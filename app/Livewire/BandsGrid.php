<?php

namespace App\Livewire;

use App\Models\BandProfile;

class BandsGrid extends SearchableGrid
{
    protected function getModelClass(): string
    {
        return BandProfile::class;
    }

    protected function getBaseQuery()
    {
        return BandProfile::with('members')->whereIn('visibility', ['public', 'members']);
    }

    protected function getCardComponent(): string
    {
        return 'band-card';
    }

    protected function getTitle(): string
    {
        return 'Find Bands';
    }

    protected function getSearchPlaceholder(): string
    {
        return 'Search bands by name...';
    }

    protected function getEmptyIcon(): string
    {
        return 'tabler:guitar-pick';
    }

    protected function getEmptyTitle(): string
    {
        return 'No bands found';
    }

    protected function getEmptyMessage(): string
    {
        return 'Try adjusting your search criteria or check back later as more bands join our community.';
    }

    protected function configureFilters()
    {
        // Add common filters
        $this->addTextFilter('name', 'Name');
        $this->addTextFilter('hometown', 'Location');

        // Add band-specific tag filters
        $this->addTagFilter('genre', 'Genre');
    }
}