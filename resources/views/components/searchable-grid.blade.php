@props([
    'title' => 'Search',
    'searchPlaceholder' => 'Search...',
    'searchName' => 'filter[name]',
    'filters' => [],
    'items',
    'totalCount' => null,
    'emptyIcon' => 'tabler:search',
    'emptyTitle' => 'No results found',
    'emptyMessage' => 'Try adjusting your search criteria.',
    'cardComponent' => null,
    'gridCols' => 3 // New prop to control filter grid columns
])

<div class="container mx-auto px-4 py-16">
    <!-- Flash Messages -->
    @if(session('info'))
    <div class="alert alert-info max-w-4xl mx-auto mb-8">
        <x-unicon name="tabler:info-circle" class="size-6"/>
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
                <div class="text-6xl mb-4"><x-unicon name="{{ $emptyIcon }}" class="size-16" /></div>
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