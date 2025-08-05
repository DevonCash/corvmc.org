<?php

namespace App\Livewire;

use App\Models\Production;

class EventsGrid extends SearchableGrid
{
    protected function getModelClass(): string
    {
        return Production::class;
    }

    protected function getBaseQuery()
    {
        return Production::publishedUpcoming();
    }

    protected function getCardComponent(): string
    {
        return 'event-card';
    }

    protected function getTitle(): string
    {
        return 'Find Events';
    }

    protected function getSearchPlaceholder(): string
    {
        return 'Search events by title...';
    }

    protected function getEmptyIcon(): string
    {
        return 'tabler:masks-theater';
    }

    protected function getEmptyTitle(): string
    {
        return 'No events found';
    }

    protected function getEmptyMessage(): string
    {
        return 'Check back soon for upcoming shows and community events!';
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
        // For now, just focus on title search - no additional filters
        // The main search field will handle title searching
    }

    // Override getItems to focus on title search only
    protected function getItems()
    {
        // Get base query from parent
        $query = $this->getBaseQuery();

        // Apply simple title search if we have search term
        if (!empty($this->search)) {
            $query->where('title', 'like', '%' . $this->search . '%');
        }

        // Debug logging when in debug mode
        if (config('app.debug') && !empty($this->search)) {
            logger('EventsGrid title search', [
                'search' => $this->search,
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings()
            ]);
        }

        return $query->paginate($this->getPerPage());
    }
}