<?php

namespace App\Livewire;

use CorvMC\Events\Models\Event;

class EventsGrid extends SearchableGrid
{
    public $scope = 'upcoming';

    protected $queryString = [
        'search' => ['except' => ''],
        'filters' => ['except' => []],
        'scope' => ['except' => 'upcoming'],
    ];

    protected function getModelClass(): string
    {
        return Event::class;
    }

    protected function getBaseQuery()
    {
        return match ($this->scope) {
            'past' => Event::publishedPast(),
            default => Event::publishedUpcoming(),
        };
    }

    protected function getCardComponent(): string
    {
        return 'events::event-card';
    }

    protected function getTitle(): string
    {
        return match ($this->scope) {
            'past' => 'Past Events',
            default => 'Upcoming Events',
        };
    }

    protected function getSearchPlaceholder(): string
    {
        return 'Search events by title, genre, or performer...';
    }

    protected function getEmptyIcon(): string
    {
        return 'tabler-masks-theater';
    }

    protected function getEmptyTitle(): string
    {
        return 'No events found';
    }

    protected function getEmptyMessage(): string
    {
        return match ($this->scope) {
            'past' => 'No past events found matching your search.',
            default => 'Check back soon for upcoming shows and community events!',
        };
    }

    public function updatedScope()
    {
        $this->resetPage();
    }

    protected function getGridCols(): int
    {
        return 4;
    }

    protected function getPerPage(): int
    {
        return 12;
    }

    protected function configureFilters()
    {
        // Override to prevent default filters from being added
        // Events only need the main search field for title searching
    }

    // Override getItems to focus on title search only
    protected function getItems()
    {
        // Get base query from parent
        $query = $this->getBaseQuery();

        // Apply case-insensitive search across title, tags, and performers if we have search term
        if (! empty($this->search)) {
            $query->where(function ($q) {
                $searchTerm = $this->search;

                // Search in title (use ilike for PostgreSQL case-insensitive search)
                $q->where('title', 'ilike', '%'.$searchTerm.'%')
                  // Search in tags/genres using spatie package methods
                    ->orWhere(function ($tagQuery) use ($searchTerm) {
                        // Get all genre tags that contain the search term
                        $genreTags = \Spatie\Tags\Tag::getWithType('genre')
                            ->filter(function ($tag) use ($searchTerm) {
                                return isset($tag->name) && stripos($tag->name, $searchTerm) !== false;
                            });

                        if ($genreTags->isNotEmpty()) {
                            $tagQuery->withAnyTags($genreTags->pluck('name')->toArray(), 'genre');
                        }
                    })
                  // Search in performer/band names
                    ->orWhereHas('performers', function ($performerQuery) use ($searchTerm) {
                        $performerQuery->where('name', 'ilike', '%'.$searchTerm.'%');
                    });
            });
        }

        return $query->paginate($this->getPerPage());
    }
}
