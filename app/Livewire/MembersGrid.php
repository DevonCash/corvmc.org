<?php

namespace App\Livewire;

use App\Models\MemberProfile;

class MembersGrid extends SearchableGrid
{
    public $scope = 'all';

    protected $queryString = [
        'search' => ['except' => ''],
        'filters' => ['except' => []],
        'scope' => ['except' => 'all'],
        'page' => ['except' => 1],
    ];

    protected function getModelClass(): string
    {
        return MemberProfile::class;
    }

    protected function getBaseQuery()
    {
        $query = MemberProfile::with(['user', 'tags'])->where('visibility', 'public');
        
        return match ($this->scope) {
            'teachers' => $query->withFlag('music_teacher'),
            default => $query,
        };
    }

    protected function getCardComponent(): string
    {
        return 'member-card';
    }

    protected function getTitle(): string
    {
        return match ($this->scope) {
            'teachers' => 'Music Teachers',
            default => 'Find Members',
        };
    }

    protected function getSearchPlaceholder(): string
    {
        return match ($this->scope) {
            'teachers' => 'Search teachers by name, skill, or genre...',
            default => 'Search members by name, skill, or genre...',
        };
    }

    protected function getEmptyIcon(): string
    {
        return 'tabler:music';
    }

    protected function getEmptyTitle(): string
    {
        return 'No members found';
    }

    protected function getEmptyMessage(): string
    {
        return match ($this->scope) {
            'teachers' => 'No music teachers found. Try adjusting your search criteria.',
            default => 'Try adjusting your search criteria or check back later for new members.',
        };
    }

    public function updatedScope()
    {
        $this->resetPage();
    }

    protected function configureFilters()
    {
        // Add skills dropdown
        $skills = \Spatie\Tags\Tag::getWithType('skill');
        if ($skills->count() > 0) {
            $this->availableFilters[] = [
                'type' => 'select',
                'key' => 'skill',
                'label' => 'Skills',
                'placeholder' => 'All Skills',
                'options' => $skills->pluck('name', 'name')->toArray(),
            ];
        }
    }

    // Override getItems to implement comprehensive search and filters
    protected function getItems()
    {
        // Get base query
        $query = $this->getBaseQuery();

        // Apply comprehensive search if we have search term
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $searchTerm = $this->search;
                
                // Search in user name
                $q->whereHas('user', function ($userQuery) use ($searchTerm) {
                      $userQuery->where('name', 'ilike', '%' . $searchTerm . '%');
                  })
                  // Search in hometown
                  ->orWhere('hometown', 'ilike', '%' . $searchTerm . '%')
                  // Search in tags using spatie package methods - find skills containing search term
                  ->orWhere(function ($skillQuery) use ($searchTerm) {
                      // Get all skill tags that contain the search term
                      $skillTags = \Spatie\Tags\Tag::getWithType('skill')
                          ->filter(function ($tag) use ($searchTerm) {
                              return stripos($tag->name, $searchTerm) !== false;
                          });
                      
                      if ($skillTags->isNotEmpty()) {
                          $skillQuery->withAnyTags($skillTags->pluck('name')->toArray(), 'skill');
                      }
                  })
                  // Search in genre tags
                  ->orWhere(function ($genreQuery) use ($searchTerm) {
                      // Get all genre tags that contain the search term
                      $genreTags = \Spatie\Tags\Tag::getWithType('genre')
                          ->filter(function ($tag) use ($searchTerm) {
                              return stripos($tag->name, $searchTerm) !== false;
                          });
                      
                      if ($genreTags->isNotEmpty()) {
                          $genreQuery->withAnyTags($genreTags->pluck('name')->toArray(), 'genre');
                      }
                  });
            });
        }

        // Apply skills filter using spatie methods
        if (!empty($this->filters['skill'])) {
            $query->withAnyTags([$this->filters['skill']], 'skill');
        }

        return $query->paginate($this->getPerPage());
    }

    protected function getPerPage(): int
    {
        return 12;
    }
}