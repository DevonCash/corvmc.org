<?php

namespace App\Livewire;

use App\Models\Band;

class BandsGrid extends SearchableGrid
{
    protected function getModelClass(): string
    {
        return Band::class;
    }

    protected function getBaseQuery()
    {
        return Band::with('members')->whereIn('visibility', ['public', 'members']);
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
        return 'Search bands by name, genre, or location...';
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
        // Add hometown dropdown
        $hometowns = Band::whereIn('visibility', ['public', 'members'])
            ->whereNotNull('hometown')
            ->where('hometown', '!=', '')
            ->distinct()
            ->pluck('hometown', 'hometown')
            ->sort();

        if ($hometowns->count() > 0) {
            $this->availableFilters[] = [
                'type' => 'select',
                'key' => 'hometown',
                'label' => 'Location',
                'placeholder' => 'All Locations',
                'options' => $hometowns->toArray(),
            ];
        }
    }

    // Override getItems to implement comprehensive search and filters
    protected function getItems()
    {
        // Get base query
        $query = $this->getBaseQuery();

        // Apply comprehensive search if we have search term
        if (! empty($this->search)) {
            $query->where(function ($q) {
                $searchTerm = $this->search;

                // Search in band name
                $q->where('name', 'ilike', '%'.$searchTerm.'%')
                  // Search in hometown
                    ->orWhere('hometown', 'ilike', '%'.$searchTerm.'%')
                  // Search in genre tags using spatie package methods
                    ->orWhere(function ($genreQuery) use ($searchTerm) {
                        // Get all genre tags that contain the search term
                        $genreTags = \Spatie\Tags\Tag::getWithType('genre')
                            ->filter(function ($tag) use ($searchTerm) {
                                return isset($tag->name) && stripos($tag->name, $searchTerm) !== false;
                            });

                        if ($genreTags->isNotEmpty()) {
                            $genreQuery->withAnyTags($genreTags->pluck('name')->toArray(), 'genre');
                        }
                    });
            });
        }

        // Apply hometown filter
        if (! empty($this->filters['hometown'])) {
            $query->where('hometown', $this->filters['hometown']);
        }

        return $query->paginate($this->getPerPage());
    }
}
