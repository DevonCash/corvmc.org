<div class="container mx-auto px-4 py-16">
    <!-- Flash Messages -->
    @if (session('info'))
        <div class="alert alert-info max-w-4xl mx-auto mb-8">
            <x-unicon name="tabler:info-circle" class="size-6" />
            <span>{{ session('info') }}</span>
        </div>
    @endif

    <!-- Header Section -->
    <div class="mb-8">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
            <div class="flex-1">
                <h2 class="text-3xl font-bold mb-2">{{ $title }}</h2>
                <p class="text-base-content/70">
                    {{ $items->total() }} {{ Str::plural('result', $items->total()) }} found
                </p>
            </div>

            <!-- Scope Selector (if scope property exists) -->
            @if (property_exists($this, 'scope'))
                <div class="tabs tabs-boxed bg-base-200">
                    @if (get_class($this) === 'App\Livewire\EventsGrid')
                        <button wire:click="$set('scope', 'upcoming')"
                            class="tab {{ $scope === 'upcoming' ? 'tab-active' : '' }}">
                            <x-unicon name="tabler:calendar-event" class="size-4 mr-2" />
                            Upcoming
                        </button>
                        <button wire:click="$set('scope', 'past')" class="tab {{ $scope === 'past' ? 'tab-active' : '' }}">
                            <x-unicon name="tabler:history" class="size-4 mr-2" />
                            Past Events
                        </button>
                    @elseif (get_class($this) === 'App\Livewire\MembersGrid')
                        <button wire:click="$set('scope', 'all')"
                            class="tab {{ $scope === 'all' ? 'tab-active' : '' }}">
                            <x-unicon name="tabler:users" class="size-4 mr-2" />
                            All Members
                        </button>
                        <button wire:click="$set('scope', 'teachers')" class="tab {{ $scope === 'teachers' ? 'tab-active' : '' }}">
                            <x-unicon name="tabler:school" class="size-4 mr-2" />
                            Teachers
                        </button>
                    @endif
                </div>
            @endif
        </div>

        <!-- Search and Filters Bar -->
        <div class="flex flex-col lg:flex-row gap-4 items-stretch lg:items-center">
            <!-- Main Search -->
            <div class="flex-1">
                <div class="relative">
                    <x-unicon name="tabler:search" class="absolute left-3 top-1/2 transform -translate-y-1/2 size-5 text-base-content/50"/>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ $searchPlaceholder }}"
                        class="input input-bordered w-full pl-10"
                    />
                </div>
            </div>

            <!-- Dynamic Filters -->
            @foreach($availableFilters as $filter)
                <div class="min-w-48">
                    @if($filter['type'] === 'text')
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="filters.{{ $filter['key'] }}"
                            placeholder="{{ $filter['placeholder'] }}"
                            class="input input-bordered w-full"
                        />
                    @elseif($filter['type'] === 'select')
                        <select wire:model.live="filters.{{ $filter['key'] }}" class="select select-bordered w-full">
                            <option value="">{{ $filter['placeholder'] }}</option>
                            @foreach($filter['options'] as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
            @endforeach

            <!-- Reset Button -->
            @if(!empty($search) || !empty($filters))
            <button wire:click="resetFilters" class="btn btn-outline btn-sm lg:btn-md">
                <x-unicon name="tabler:x" class="size-4"/>
                Clear
            </button>
            @endif
        </div>

    </div>

    <!-- Loading State -->
    <div wire:loading class="flex justify-center py-8">
        <div class="loading loading-spinner loading-lg"></div>
    </div>

    <!-- Results Grid -->
    <div wire:loading.remove class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-6 mb-8">
        @forelse($items as $item)
            @if ($cardComponent)
                <x-dynamic-component :component="$cardComponent" :item="$item" />
            @else
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h3 class="card-title">{{ $item->name ?? ($item->title ?? 'Item') }}</h3>
                        @if (isset($item->description))
                            <p class="text-sm opacity-70">{{ Str::limit($item->description, 100) }}</p>
                        @endif
                    </div>
                </div>
            @endif
        @empty
            <div class="col-span-full text-center py-16">
                <div class="text-6xl mb-4">
                    <x-unicon name="{{ $emptyIcon }}" class="size-16" />
                </div>
                <h3 class="text-2xl font-bold mb-4">{{ $emptyTitle }}</h3>
                <p class="text-lg opacity-70">{{ $emptyMessage }}</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if ($items->hasPages())
        <div class="flex justify-center mt-8">
            {{ $items->links() }}
        </div>
    @endif
</div>
