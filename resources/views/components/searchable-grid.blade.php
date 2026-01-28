@props([
    'title' => 'Search',
    'searchPlaceholder' => 'Search...',
    'searchName' => 'filter[name]',
    'filters' => [],
    'items',
    'totalCount' => null,
    'emptyIcon' => 'tabler-search',
    'emptyTitle' => 'No results found',
    'emptyMessage' => 'Try adjusting your search criteria.',
    'cardComponent' => null,
    'gridCols' => 3 // New prop to control filter grid columns
])

<div class="container mx-auto px-4 py-16">
    <!-- Flash Messages -->
    @if(session('info'))
    <div class="alert alert-info max-w-4xl mx-auto mb-8">
        <x-icon name="tabler-info-circle" class="size-6"/>
        <span>{{ session('info') }}</span>
    </div>
    @endif

    <!-- Search and Filters -->
    <form class="card bg-base-100 shadow-lg mb-8">
        <div class="card-body">
            <h2 class="card-title mb-4">{{ $title }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-{{ $gridCols }} gap-4">
                <!-- Search Input -->
                <fieldset class="fieldset col-span-{{ $gridCols }}">
                    <legend class="fieldset-legend">
                        {{ $searchPlaceholder }}
                    </legend>
                    <input 
                        type="text" 
                        placeholder="{{ $searchPlaceholder }}" 
                        name="{{ $searchName }}"
                        value="{{ request($searchName) }}"
                        class="input input-bordered w-full" 
                    />
                </fieldset>

                <!-- Dynamic Filters -->
                @foreach($filters as $filter)
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">
                            {{ $filter['label'] }}
                        </legend>
                        <select name="{{ $filter['name'] }}" class="select select-bordered w-full">
                            <option value="">{{ $filter['placeholder'] ?? 'All ' . $filter['label'] }}</option>
                            @foreach($filter['options'] as $value => $label)
                                <option 
                                    value="{{ $value }}" 
                                    {{ request($filter['name']) == $value ? 'selected' : '' }}
                                >
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </fieldset>
                @endforeach
            </div>

            <div class="flex justify-between items-center mt-4">
                <button class="btn btn-primary">Search</button>
                @if($totalCount !== null)
                    <span class="text-sm opacity-70">{{ $totalCount }} {{ Str::plural('result', $totalCount) }} found</span>
                @endif
            </div>
        </div>
    </form>

    <!-- Active Filters Display -->
    @php
        // Handle nested filter arrays properly
        $allParams = request()->query();
        $activeFilters = collect();
        
        // Flatten nested filter arrays like filter[skill] => value
        foreach ($allParams as $key => $value) {
            if ($key === 'page') continue;
            
            if ($key === 'filter' && is_array($value)) {
                // Handle filter[key] => value structure
                foreach ($value as $filterKey => $filterValue) {
                    if (!empty($filterValue)) {
                        $activeFilters->put("filter[{$filterKey}]", $filterValue);
                    }
                }
            } elseif (!empty($value) && !is_array($value)) {
                // Handle direct key => value
                $activeFilters->put($key, $value);
            }
        }
        
        $hasActiveFilters = $activeFilters->isNotEmpty();
    @endphp

    @if($hasActiveFilters)
    <div class="flex flex-wrap items-center gap-2 mb-6">
        <span class="text-sm font-medium opacity-70">Active filters:</span>
        
        @foreach($activeFilters as $key => $value)
            @php
                // Skip if value is an array or empty
                if (is_array($value) || empty($value)) {
                    continue;
                }
                
                // Convert value to string
                $value = (string) $value;
                
                // Parse filter keys like 'filter[skill]' to get the label
                $filterKey = str_replace(['filter[', ']'], '', $key);
                $filterLabel = '';
                $displayValue = $value;
                
                // Find the matching filter configuration
                foreach($filters as $filter) {
                    if ($filter['name'] === $key) {
                        $filterLabel = $filter['label'];
                        // If it's a select filter with options, get the display label
                        if (isset($filter['options'][$value])) {
                            $displayValue = $filter['options'][$value];
                        }
                        break;
                    }
                }
                
                // Fallback labels for common filter patterns
                if (empty($filterLabel)) {
                    $filterLabel = match($filterKey) {
                        'name' => 'Name',
                        'skill' => 'Skill',
                        'genre' => 'Genre', 
                        'flag' => 'Looking For',
                        'hometown' => 'Location',
                        default => ucfirst($filterKey)
                    };
                }
                
                // Create removal URL - handle nested filter structure
                $removeParams = request()->query();
                if (str_starts_with($key, 'filter[') && str_ends_with($key, ']')) {
                    $filterKey = str_replace(['filter[', ']'], '', $key);
                    if (isset($removeParams['filter'][$filterKey])) {
                        unset($removeParams['filter'][$filterKey]);
                        if (empty($removeParams['filter'])) {
                            unset($removeParams['filter']);
                        }
                    }
                } else {
                    unset($removeParams[$key]);
                }
                $removeUrl = request()->url() . '?' . http_build_query($removeParams);
            @endphp
            
            <div class="filter">
                <span class="filter-label">{{ $filterLabel }}</span>
                <span class="filter-value">{{ $displayValue }}</span>
                <a href="{{ $removeUrl }}" class="filter-remove">
                    <x-icon name="tabler-x" class="size-3" />
                </a>
            </div>
        @endforeach
        
        <!-- Clear All Filters Button -->
        <a href="{{ request()->url() }}" class="btn btn-outline btn-xs">
            <x-icon name="tabler-x" class="size-3" />
            Clear All
        </a>
    </div>
    @endif

    <!-- Results Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
        @forelse($items as $item)
            @if($cardComponent)
                <x-dynamic-component :component="$cardComponent" :item="$item" />
            @else
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <p>{{ $item->name ?? 'Item' }}</p>
                    </div>
                </div>
            @endif
        @empty
            <div class="col-span-full text-center py-16">
                <div class="text-6xl mb-4"><x-icon name="{{ $emptyIcon  }}" class="size-16" /></div>
                <h3 class="text-2xl font-bold mb-4">{{ $emptyTitle }}</h3>
                <p class="text-lg opacity-70">{{ $emptyMessage }}</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if(method_exists($items, 'hasPages') && $items->hasPages())
        <div class="flex justify-center">
            <div class="join">
                @if ($items->onFirstPage())
                    <button class="join-item btn btn-disabled">«</button>
                @else
                    <a href="{{ $items->previousPageUrl() }}" class="join-item btn">«</a>
                @endif

                @for ($i = 1; $i <= $items->lastPage(); $i++)
                    @if ($i == $items->currentPage())
                        <button class="join-item btn btn-active">{{ $i }}</button>
                    @else
                        <a href="{{ $items->url($i) }}" class="join-item btn">{{ $i }}</a>
                    @endif
                @endfor

                @if ($items->hasMorePages())
                    <a href="{{ $items->nextPageUrl() }}" class="join-item btn">»</a>
                @else
                    <button class="join-item btn btn-disabled">»</button>
                @endif
            </div>
        </div>
    @endif
</div>