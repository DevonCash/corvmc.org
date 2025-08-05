<?php

namespace App\Livewire;

use App\Models\MemberProfile;

class MembersGrid extends SearchableGrid
{
    protected function getModelClass(): string
    {
        return MemberProfile::class;
    }

    protected function getBaseQuery()
    {
        return MemberProfile::with('user')->whereIn('visibility', ['public']);
    }

    protected function getCardComponent(): string
    {
        return 'member-card';
    }

    protected function getTitle(): string
    {
        return 'Find Musicians';
    }

    protected function getSearchPlaceholder(): string
    {
        return 'Search musicians by name...';
    }

    protected function getEmptyIcon(): string
    {
        return 'tabler:music';
    }

    protected function getEmptyTitle(): string
    {
        return 'No musicians found';
    }

    protected function getEmptyMessage(): string
    {
        return 'Try adjusting your search criteria or check back later.';
    }

    protected function configureFilters()
    {
        // Add common filters
        $this->addTextFilter('name', 'Name');
        $this->addTextFilter('hometown', 'Location');

        // Add member-specific tag filters
        $this->addTagFilter('genre', 'Genre');
        $this->addTagFilter('skill', 'Skills');
    }
}