<x-public.layout title="Equipment Library | Corvallis Music Collective">
    <!-- Hero Section -->
    <div class="hero min-h-96 bg-gradient-to-r from-info/10 to-cyan/10">
        <div class="hero-content text-center">
            <div class="max-w-2xl">
                <h1 class="text-5xl font-bold">Equipment Library</h1>
                <p class="py-6 text-lg">
                    Browse our gear lending library - quality instruments and equipment available to CMC members
                </p>
                <div class="stats stats-horizontal shadow bg-base-100/80 backdrop-blur">
                    <div class="stat">
                        <div class="stat-value text-info">{{ $statistics['total_equipment'] }}</div>
                        <div class="stat-title">Total Items</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value text-success">{{ $statistics['available_equipment'] }}</div>
                        <div class="stat-title">Available</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value text-warning">{{ $statistics['checked_out_equipment'] }}</div>
                        <div class="stat-title">In Use</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-16">
        <!-- Filters -->
        <div class="bg-base-100 rounded-xl shadow-lg p-6 mb-8">
            <form method="GET" class="space-y-4 lg:space-y-0 lg:flex lg:items-end lg:gap-4">
                <!-- Search -->
                <div class="form-control flex-1">
                    <label class="label">
                        <span class="label-text font-semibold">Search Equipment</span>
                    </label>
                    <input type="text" 
                           name="search" 
                           value="{{ request('search') }}"
                           placeholder="Search by name, brand, or model..." 
                           class="input input-bordered w-full" />
                </div>

                <!-- Type Filter -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Type</span>
                    </label>
                    <select name="type" class="select select-bordered w-full max-w-xs">
                        <option value="">All Types</option>
                        @foreach($equipmentTypes as $type)
                            <option value="{{ $type }}" 
                                    {{ request('type') === $type ? 'selected' : '' }}>
                                {{ ucfirst($type) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Availability Filter -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Availability</span>
                    </label>
                    <select name="availability" class="select select-bordered w-full max-w-xs">
                        <option value="">All Items</option>
                        <option value="available" {{ request('availability') === 'available' ? 'selected' : '' }}>
                            Available Now
                        </option>
                        <option value="checked_out" {{ request('availability') === 'checked_out' ? 'selected' : '' }}>
                            Currently In Use
                        </option>
                    </select>
                </div>

                <!-- Filter Button -->
                <div class="form-control">
                    <button type="submit" class="btn btn-primary">
                        <x-unicon name="tabler:search" class="size-4" />
                        Filter
                    </button>
                </div>

                <!-- Clear Filters -->
                @if(request()->hasAny(['search', 'type', 'availability']))
                    <div class="form-control">
                        <a href="{{ route('equipment.index') }}" class="btn btn-ghost">
                            Clear Filters
                        </a>
                    </div>
                @endif
            </form>
        </div>

        <!-- Equipment Grid -->
        @if($equipment->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
                @foreach($equipment as $item)
                    <div class="card bg-base-100 shadow-lg hover:shadow-xl transition-shadow">
                        <!-- Equipment Photo -->
                        <figure class="px-4 pt-4">
                            @if($item->getFirstMediaUrl('equipment-photos'))
                                <img src="{{ $item->getFirstMediaUrl('equipment-photos', 'thumb') }}" 
                                     alt="{{ $item->name }}" 
                                     class="rounded-xl h-48 w-full object-cover" />
                            @else
                                <div class="bg-base-200 rounded-xl h-48 w-full flex items-center justify-center">
                                    <x-unicon name="tabler:music" class="size-16 text-base-content/30" />
                                </div>
                            @endif
                        </figure>

                        <div class="card-body">
                            <!-- Equipment Type Badge -->
                            <div class="badge badge-outline badge-sm mb-2">
                                {{ ucfirst($item->type) }}
                            </div>

                            <!-- Equipment Name -->
                            <h3 class="card-title text-lg">{{ $item->name }}</h3>
                            
                            <!-- Brand & Model -->
                            @if($item->brand || $item->model)
                                <p class="text-sm text-base-content/70">
                                    {{ collect([$item->brand, $item->model])->filter()->join(' ') }}
                                </p>
                            @endif

                            <!-- Availability Status -->
                            <div class="flex items-center gap-2 mt-2">
                                @if($item->is_available)
                                    <div class="badge badge-success gap-1">
                                        <x-unicon name="tabler:check" class="size-3" />
                                        Available
                                    </div>
                                @else
                                    <div class="badge badge-warning gap-1">
                                        <x-unicon name="tabler:clock" class="size-3" />
                                        In Use
                                    </div>
                                    @if($item->currentLoan)
                                        <span class="text-xs text-base-content/60">
                                            Until {{ $item->currentLoan->due_at->format('M j') }}
                                        </span>
                                    @endif
                                @endif
                            </div>

                            <!-- Condition -->
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-xs text-base-content/60">Condition:</span>
                                <div class="badge badge-sm
                                    {{ $item->condition === 'excellent' ? 'badge-success' : '' }}
                                    {{ $item->condition === 'good' ? 'badge-primary' : '' }}
                                    {{ $item->condition === 'fair' ? 'badge-warning' : '' }}
                                    {{ in_array($item->condition, ['poor', 'needs_repair']) ? 'badge-error' : '' }}
                                ">
                                    {{ ucfirst(str_replace('_', ' ', $item->condition)) }}
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="card-actions justify-end mt-4">
                                <a href="{{ route('equipment.show', $item) }}" 
                                   class="btn btn-primary btn-sm">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="flex justify-center">
                {{ $equipment->links() }}
            </div>
        @else
            <!-- No Equipment Found -->
            <div class="text-center py-16">
                <x-unicon name="tabler:search-off" class="size-16 mx-auto text-base-content/30 mb-4" />
                <h3 class="text-2xl font-bold mb-2">No Equipment Found</h3>
                <p class="text-base-content/70 mb-4">
                    Try adjusting your search criteria or clearing the filters.
                </p>
                <a href="{{ route('equipment.index') }}" class="btn btn-primary">
                    View All Equipment
                </a>
            </div>
        @endif

        <!-- How to Borrow/Contact Section -->
        @php
            $equipmentSettings = app(\App\Settings\EquipmentSettings::class);
        @endphp
        <div class="bg-gradient-to-br from-primary/5 to-secondary/10 rounded-3xl p-8 mt-16">
            <div class="text-center mb-8">
                @if($equipmentSettings->enable_rental_features)
                    <h2 class="text-3xl font-bold mb-4">How to Borrow Equipment</h2>
                    <p class="text-lg max-w-3xl mx-auto">
                        Ready to borrow some gear? Here's how our lending library works.
                    </p>
                @else
                    <h2 class="text-3xl font-bold mb-4">Interested in Our Equipment?</h2>
                    <p class="text-lg max-w-3xl mx-auto">
                        Explore our gear collection and contact us to learn about availability and access.
                    </p>
                @endif
            </div>

            @if($equipmentSettings->enable_rental_features)
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="text-center">
                        <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center mx-auto mb-4">
                            <x-unicon name="tabler:user-check" class="size-8 text-primary-content" />
                        </div>
                        <h3 class="text-xl font-bold mb-2">1. Be a Member</h3>
                        <p class="text-base-content/70">
                            Equipment lending is available to CMC members in good standing.
                        </p>
                    </div>

                    <div class="text-center">
                        <div class="w-16 h-16 bg-secondary rounded-full flex items-center justify-center mx-auto mb-4">
                            <x-unicon name="tabler:message" class="size-8 text-secondary-content" />
                        </div>
                        <h3 class="text-xl font-bold mb-2">2. Contact Us</h3>
                        <p class="text-base-content/70">
                            Reach out via email or in person to request equipment loans.
                        </p>
                    </div>

                    <div class="text-center">
                        <div class="w-16 h-16 bg-accent rounded-full flex items-center justify-center mx-auto mb-4">
                            <x-unicon name="tabler:calendar" class="size-8 text-accent-content" />
                        </div>
                        <h3 class="text-xl font-bold mb-2">3. Schedule Pickup</h3>
                        <p class="text-base-content/70">
                            We'll coordinate pickup times and handle any deposits or fees.
                        </p>
                    </div>
                </div>
            @else
                <div class="text-center max-w-2xl mx-auto">
                    <div class="w-20 h-20 bg-primary/20 rounded-full flex items-center justify-center mx-auto mb-6">
                        <x-unicon name="tabler:info-circle" class="size-10 text-primary" />
                    </div>
                    <p class="text-lg text-base-content/80 mb-4">
                        This equipment catalog showcases instruments and gear available through CMC. 
                        Contact us to learn more about access and availability for members.
                    </p>
                </div>
            @endif

            <div class="text-center mt-8">
                @if($equipmentSettings->enable_rental_features)
                    <a href="{{ route('contact') }}?topic=gear" class="btn btn-primary btn-lg">
                        Request Equipment Loan
                    </a>
                @else
                    <a href="{{ route('contact') }}?topic=gear" class="btn btn-primary btn-lg">
                        Contact About Equipment
                    </a>
                @endif
                <a href="{{ route('members.index') }}" class="btn btn-outline btn-secondary btn-lg">
                    Become a Member
                </a>
            </div>
        </div>
    </div>
</x-public.layout>