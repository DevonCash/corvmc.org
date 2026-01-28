<div class="container mx-auto px-4 py-16">
    <!-- Flash Messages -->
    @if(session('info'))
    <div class="alert alert-info max-w-4xl mx-auto mb-8">
        <x-icon name="tabler-info-circle" class="size-6"/>
        <span>{{ session('info') }}</span>
    </div>
    @endif

    <!-- Search and Filters -->
    <div class="card bg-base-100 shadow-lg mb-8">
        <div class="card-body">
            <h2 class="card-title mb-4">{{ $title }}</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-{{ $gridCols }} gap-4">
                <!-- Main Search -->
                <div class="col-span-{{ $gridCols }}">
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">{{ $searchPlaceholder }}</span>
                        </label>
                        <input 
                            type="text" 
                            wire:model.live.debounce.300ms="search"
                            placeholder="{{ $searchPlaceholder }}" 
                            class="input input-bordered w-full" 
                        />
                    </div>
                </div>

                <!-- Dynamic Filters -->
                @foreach($availableFilters as $filter)
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">{{ $filter['label'] }}</span>
                        </label>
                        
                        @if($filter['type'] === 'text')
                            <input 
                                type="text" 
                                wire:model.live.debounce.300ms="filters.{{ $filter['key'] }}"
                                placeholder="{{ $filter['placeholder'] }}" 
                                class="input input-bordered" 
                            />
                        @elseif($filter['type'] === 'select')
                            <select wire:model.live="filters.{{ $filter['key'] }}" class="select select-bordered">
                                <option value="">{{ $filter['placeholder'] }}</option>
                                @foreach($filter['options'] as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="flex justify-between items-center mt-4">
                <button wire:click="resetFilters" class="btn btn-outline">
                    <x-icon name="tabler-refresh" class="size-4"/>
                    Reset Filters
                </button>
                <span class="text-sm opacity-70">
                    {{ $items->total() }} {{ Str::plural('result', $items->total()) }} found
                </span>
            </div>
            
            {{-- Debug: Show available filters --}}
            @if(config('app.debug'))
            <div class="text-xs opacity-50 mt-2 p-2 bg-gray-100 rounded">
                <div>Debug: {{ count($availableFilters) }} filters available</div>
                @if($availableFilters)
                    <div>Filters: {{ json_encode(array_column($availableFilters, 'key')) }}</div>
                @endif
                @if(!empty($search) || !empty($filters))
                    <div>Active: search="{{ $search }}" filters={{ json_encode($filters) }}</div>
                @endif
                <div>Total items: {{ $items->total() }}</div>
            </div>
            @endif
        </div>
    </div>

    <!-- Loading State -->
    <div wire:loading class="flex justify-center py-8">
        <div class="loading loading-spinner loading-lg"></div>
    </div>

    <!-- Results Grid -->
    <div wire:loading.remove class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
        @forelse($items as $item)
            @if($cardComponent)
                <x-dynamic-component :component="$cardComponent" :item="$item" />
            @else
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h3 class="card-title">{{ $item->name ?? $item->title ?? 'Item' }}</h3>
                        @if(isset($item->description))
                            <p class="text-sm opacity-70">{{ Str::limit($item->description, 100) }}</p>
                        @endif
                    </div>
                </div>
            @endif
        @empty
            <div class="col-span-full text-center py-16">
                <div class="text-6xl mb-4">
                    <x-icon name="{{ $emptyIcon  }}" class="size-16" />
                </div>
                <h3 class="text-2xl font-bold mb-4">{{ $emptyTitle }}</h3>
                <p class="text-lg opacity-70">{{ $emptyMessage }}</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($items->hasPages())
        <div class="flex justify-center mt-8">
            {{ $items->links() }}
        </div>
    @endif
</div>