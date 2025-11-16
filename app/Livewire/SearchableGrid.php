<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Tags\Tag;

abstract class SearchableGrid extends Component
{
    use WithPagination;

    // Filter state
    public $search = '';

    public $filters = [];

    // Generated controls
    public $availableFilters = [];

    // Abstract methods that child classes must implement
    abstract protected function getModelClass(): string;

    abstract protected function getBaseQuery();

    abstract protected function getCardComponent(): string;

    // Methods child classes can override
    protected function getTitle(): string
    {
        return 'Search';
    }

    protected function getSearchPlaceholder(): string
    {
        return 'Search...';
    }

    protected function getEmptyIcon(): string
    {
        return 'tabler:search';
    }

    protected function getEmptyTitle(): string
    {
        return 'No results found';
    }

    protected function getEmptyMessage(): string
    {
        return 'Try adjusting your search criteria.';
    }

    protected function getGridCols(): int
    {
        return 3;
    }

    protected function getPerPage(): int
    {
        return 24;
    }

    protected $queryString = [
        'search' => ['except' => ''],
        'filters' => ['except' => []],
    ];

    public function mount()
    {
        $this->generateAvailableFilters();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFilters()
    {
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->search = '';
        $this->filters = [];
        $this->resetPage();
    }

    protected function generateAvailableFilters()
    {
        $this->availableFilters = [];

        // Let child classes define their own filters
        $this->configureFilters();
    }

    // Child classes can override this to define their filters
    protected function configureFilters()
    {
        $modelClass = $this->getModelClass();
        $modelInstance = new $modelClass;

        // Check for common filterable fields
        $this->addTextFilter('name', 'Name');
        $this->addTextFilter('title', 'Title');
        $this->addTextFilter('hometown', 'Location');

        // Check for tags (genres, skills, etc.)
        if (method_exists($modelInstance, 'tagsWithType')) {
            $this->addTagFilter('genre', 'Genre');
            $this->addTagFilter('skill', 'Skills');
        }
    }

    protected function addTextFilter($field, $label)
    {
        $modelClass = $this->getModelClass();
        $modelInstance = new $modelClass;

        // Check if field exists in fillable or database
        $fieldExists = in_array($field, $modelInstance->getFillable());

        // For now, add common fields without database check to avoid issues
        if ($fieldExists || in_array($field, ['name', 'title', 'hometown'])) {
            $this->availableFilters[] = [
                'type' => 'text',
                'key' => $field,
                'label' => $label,
                'placeholder' => "Search by {$label}...",
            ];
        }
    }

    protected function addTagFilter($tagType, $label)
    {
        $tags = Tag::getWithType($tagType);
        if ($tags->count() > 0) {
            $this->availableFilters[] = [
                'type' => 'select',
                'key' => "withAllTags_{$tagType}",
                'label' => $label,
                'placeholder' => "All {$label}",
                'options' => $tags->pluck('name', 'name')->toArray(),
            ];
        }
    }

    protected function addDateRangeFilter()
    {
        $this->availableFilters[] = [
            'type' => 'select',
            'key' => 'dateRange',
            'label' => 'Date',
            'placeholder' => 'All Dates',
            'options' => [
                'this_week' => 'This Week',
                'this_month' => 'This Month',
                'next_month' => 'Next Month',
            ],
        ];
    }

    protected function addVenueFilter()
    {
        $this->availableFilters[] = [
            'type' => 'select',
            'key' => 'venue',
            'label' => 'Venue',
            'placeholder' => 'All Venues',
            'options' => [
                'cmc' => 'CMC Main Room',
                'external' => 'External Venues',
            ],
        ];
    }

    public function render()
    {
        $items = $this->getItems();

        return view('livewire.searchable-grid', [
            'items' => $items,
            'title' => $this->getTitle(),
            'searchPlaceholder' => $this->getSearchPlaceholder(),
            'emptyIcon' => $this->getEmptyIcon(),
            'emptyTitle' => $this->getEmptyTitle(),
            'emptyMessage' => $this->getEmptyMessage(),
            'cardComponent' => $this->getCardComponent(),
            'gridCols' => $this->getGridCols(),
        ]);
    }

    protected function getItems()
    {
        // Get base query from child class
        $query = $this->getBaseQuery();

        // Build search filter
        if (! empty($this->search)) {
            $searchableFields = $this->getSearchableFields();
            if (! empty($searchableFields)) {
                $query->where(function ($q) use ($searchableFields) {
                    foreach ($searchableFields as $field) {
                        $q->orWhere($field, 'like', '%'.$this->search.'%');
                    }
                });
            }
        }

        // Build dynamic filters
        foreach ($this->availableFilters as $filter) {
            $filterKey = $filter['key'];

            if (isset($this->filters[$filterKey]) && ! empty($this->filters[$filterKey])) {
                switch ($filter['type']) {
                    case 'text':
                        // Apply text filter directly
                        $query->where($filterKey, 'like', '%'.$this->filters[$filterKey].'%');
                        break;
                    case 'select':
                        if (str_starts_with($filterKey, 'withAllTags_')) {
                            $tagType = str_replace('withAllTags_', '', $filterKey);
                            $query->withAnyTags([$this->filters[$filterKey]], $tagType);
                        } else {
                            // Apply scope filter
                            $query->{$filterKey}($this->filters[$filterKey]);
                        }
                        break;
                }
            }
        }

        return $query->paginate($this->getPerPage());
    }

    protected function getSearchableFields()
    {
        $modelClass = $this->getModelClass();
        $modelInstance = new $modelClass;
        $searchableFields = [];

        $commonFields = ['name', 'title', 'description'];
        foreach ($commonFields as $field) {
            if (in_array($field, $modelInstance->getFillable())) {
                $searchableFields[] = $field;
            }
        }

        return $searchableFields;
    }
}
