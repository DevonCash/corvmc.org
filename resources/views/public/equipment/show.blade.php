<x-public.layout title="{{ $equipment->name }} | Equipment Library | Corvallis Music Collective">
    <div class="container mx-auto px-4 py-8">
        <!-- Breadcrumbs -->
        <div class="breadcrumbs text-sm mb-8">
            <ul>
                <li><a href="{{ route('home') }}">Home</a></li>
                <li><a href="{{ route('equipment.index') }}">Equipment Library</a></li>
                <li class="font-semibold">{{ $equipment->name }}</li>
            </ul>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-12">
            <!-- Equipment Photos -->
            <div class="space-y-4">
                @if($equipment->getMedia('equipment-photos')->count() > 0)
                    <!-- Main Photo -->
                    <div class="rounded-2xl overflow-hidden shadow-lg">
                        <img src="{{ $equipment->getFirstMediaUrl('equipment-photos') }}" 
                             alt="{{ $equipment->name }}" 
                             class="w-full h-96 object-cover" 
                             id="mainPhoto" />
                    </div>

                    <!-- Photo Thumbnails -->
                    @if($equipment->getMedia('equipment-photos')->count() > 1)
                        <div class="flex gap-2 overflow-x-auto">
                            @foreach($equipment->getMedia('equipment-photos') as $photo)
                                <img src="{{ $photo->getUrl('thumb') }}" 
                                     alt="{{ $equipment->name }}" 
                                     class="w-20 h-20 object-cover rounded-lg cursor-pointer hover:opacity-75 transition-opacity flex-shrink-0
                                            {{ $loop->first ? 'ring-2 ring-primary' : '' }}"
                                     onclick="document.getElementById('mainPhoto').src = '{{ $photo->getUrl() }}'; 
                                              document.querySelectorAll('.w-20').forEach(img => img.classList.remove('ring-2', 'ring-primary')); 
                                              this.classList.add('ring-2', 'ring-primary');" />
                            @endforeach
                        </div>
                    @endif
                @else
                    <!-- Placeholder Image -->
                    <div class="bg-base-200 rounded-2xl h-96 flex items-center justify-center">
                        <div class="text-center">
                            <x-unicon name="tabler:music" class="size-24 text-base-content/30 mx-auto mb-4" />
                            <p class="text-base-content/50">No photos available</p>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Equipment Details -->
            <div class="space-y-6">
                <!-- Header -->
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="badge badge-outline">{{ ucfirst($equipment->type) }}</div>
                        @if($equipment->is_available)
                            <div class="badge badge-success gap-1">
                                <x-unicon name="tabler:check" class="size-3" />
                                Available
                            </div>
                        @else
                            <div class="badge badge-warning gap-1">
                                <x-unicon name="tabler:clock" class="size-3" />
                                In Use
                            </div>
                        @endif
                    </div>
                    
                    <h1 class="text-4xl font-bold">{{ $equipment->name }}</h1>
                    
                    @if($equipment->brand || $equipment->model)
                        <p class="text-xl text-base-content/70 mt-2">
                            {{ collect([$equipment->brand, $equipment->model])->filter()->join(' ') }}
                        </p>
                    @endif
                </div>

                <!-- Key Details -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <h3 class="font-semibold text-base-content/70 mb-1">Condition</h3>
                        <div class="badge
                            {{ $equipment->condition === 'excellent' ? 'badge-success' : '' }}
                            {{ $equipment->condition === 'good' ? 'badge-primary' : '' }}
                            {{ $equipment->condition === 'fair' ? 'badge-warning' : '' }}
                            {{ in_array($equipment->condition, ['poor', 'needs_repair']) ? 'badge-error' : '' }}
                        ">
                            {{ ucfirst(str_replace('_', ' ', $equipment->condition)) }}
                        </div>
                    </div>

                    @if($equipment->serial_number)
                        <div>
                            <h3 class="font-semibold text-base-content/70 mb-1">Serial Number</h3>
                            <p class="font-mono text-sm">{{ $equipment->serial_number }}</p>
                        </div>
                    @endif

                    @if($equipment->location)
                        <div>
                            <h3 class="font-semibold text-base-content/70 mb-1">Location</h3>
                            <p>{{ $equipment->location }}</p>
                        </div>
                    @endif

                    <div>
                        <h3 class="font-semibold text-base-content/70 mb-1">Acquisition</h3>
                        <div class="badge badge-ghost">
                            {{ ucfirst(str_replace('_', ' ', $equipment->acquisition_type)) }}
                        </div>
                    </div>
                </div>

                <!-- Current Status -->
                @if(!$equipment->is_available && $equipment->currentLoan)
                    <div class="alert alert-warning">
                        <x-unicon name="tabler:info-circle" class="size-5" />
                        <div>
                            <p><strong>Currently checked out</strong></p>
                            <p class="text-sm">Expected return: {{ $equipment->currentLoan->due_at->format('l, F j, Y') }}</p>
                        </div>
                    </div>
                @endif

                <!-- Description -->
                @if($equipment->description)
                    <div>
                        <h3 class="font-semibold mb-2">Description</h3>
                        <p class="text-base-content/80">{{ $equipment->description }}</p>
                    </div>
                @endif

                <!-- Actions -->
                @php
                    $equipmentSettings = app(\App\Settings\EquipmentSettings::class);
                @endphp
                <div class="space-y-3">
                    @if($equipmentSettings->enable_rental_features)
                        @if($equipment->is_available)
                            <a href="{{ route('contact') }}?topic=gear&equipment={{ $equipment->id }}" 
                               class="btn btn-primary btn-lg w-full">
                                <x-unicon name="tabler:calendar-plus" class="size-5" />
                                Request to Borrow
                            </a>
                        @else
                            <button class="btn btn-disabled btn-lg w-full">
                                <x-unicon name="tabler:clock" class="size-5" />
                                Currently Unavailable
                            </button>
                        @endif
                    @else
                        <a href="{{ route('contact') }}?topic=gear&equipment={{ $equipment->id }}" 
                           class="btn btn-primary btn-lg w-full">
                            <x-unicon name="tabler:info-circle" class="size-5" />
                            Contact About This Item
                        </a>
                    @endif
                    
                    <a href="{{ route('equipment.index') }}" class="btn btn-outline w-full">
                        <x-unicon name="tabler:arrow-left" class="size-5" />
                        Back to Equipment Library
                    </a>
                </div>

                <!-- Donor Recognition -->
                @if($equipment->isDonated() && $equipment->provider)
                    <div class="bg-success/10 border border-success/20 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <x-unicon name="tabler:heart" class="size-5 text-success" />
                            <h3 class="font-semibold text-success">Generously Donated</h3>
                        </div>
                        <p class="text-sm">
                            This equipment was donated to CMC by <strong>{{ $equipment->provider->name }}</strong>.
                            Thank you for supporting our community!
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Additional Information -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
            <!-- Recent Loan History -->
            @if($equipment->loans->count() > 0)
                <div class="bg-base-100 rounded-xl shadow-lg p-6">
                    <h2 class="text-2xl font-bold mb-4 flex items-center gap-2">
                        <x-unicon name="tabler:history" class="size-6" />
                        Recent Activity
                    </h2>
                    
                    <div class="space-y-3">
                        @foreach($equipment->loans->take(5) as $loan)
                            <div class="flex items-center justify-between p-3 bg-base-200 rounded-lg">
                                <div>
                                    <p class="font-medium">{{ $loan->borrower->name }}</p>
                                    <p class="text-sm text-base-content/60">
                                        {{ $loan->checked_out_at->format('M j, Y') }}
                                        @if($loan->returned_at)
                                            - {{ $loan->returned_at->format('M j, Y') }}
                                        @else
                                            - Present
                                        @endif
                                    </p>
                                </div>
                                <div class="badge badge-sm
                                    {{ $loan->status === 'returned' ? 'badge-success' : '' }}
                                    {{ $loan->status === 'active' ? 'badge-warning' : '' }}
                                    {{ $loan->status === 'overdue' ? 'badge-error' : '' }}
                                ">
                                    {{ ucfirst($loan->status) }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Equipment Specifications -->
            <div class="bg-base-100 rounded-xl shadow-lg p-6">
                <h2 class="text-2xl font-bold mb-4 flex items-center gap-2">
                    <x-unicon name="tabler:info-circle" class="size-6" />
                    Specifications
                </h2>
                
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-base-content/70">Type</span>
                        <span class="font-medium">{{ ucfirst($equipment->type) }}</span>
                    </div>
                    
                    @if($equipment->brand)
                        <div class="flex justify-between">
                            <span class="text-base-content/70">Brand</span>
                            <span class="font-medium">{{ $equipment->brand }}</span>
                        </div>
                    @endif
                    
                    @if($equipment->model)
                        <div class="flex justify-between">
                            <span class="text-base-content/70">Model</span>
                            <span class="font-medium">{{ $equipment->model }}</span>
                        </div>
                    @endif
                    
                    <div class="flex justify-between">
                        <span class="text-base-content/70">Condition</span>
                        <span class="font-medium">{{ ucfirst(str_replace('_', ' ', $equipment->condition)) }}</span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-base-content/70">Added to Library</span>
                        <span class="font-medium">{{ $equipment->acquisition_date->format('M Y') }}</span>
                    </div>

                    @if($equipment->notes)
                        <div class="border-t pt-3 mt-3">
                            <span class="text-base-content/70 block mb-1">Notes</span>
                            <p class="text-sm">{{ $equipment->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Related Equipment -->
        @if($relatedEquipment->count() > 0)
            <div class="mb-12">
                <h2 class="text-3xl font-bold mb-8 text-center">More {{ ucfirst($equipment->type) }} Equipment</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    @foreach($relatedEquipment as $item)
                        <div class="card bg-base-100 shadow-lg hover:shadow-xl transition-shadow">
                            <figure class="px-4 pt-4">
                                @if($item->getFirstMediaUrl('equipment-photos'))
                                    <img src="{{ $item->getFirstMediaUrl('equipment-photos', 'thumb') }}" 
                                         alt="{{ $item->name }}" 
                                         class="rounded-xl h-32 w-full object-cover" />
                                @else
                                    <div class="bg-base-200 rounded-xl h-32 w-full flex items-center justify-center">
                                        <x-unicon name="tabler:music" class="size-8 text-base-content/30" />
                                    </div>
                                @endif
                            </figure>

                            <div class="card-body p-4">
                                <h3 class="card-title text-base">{{ $item->name }}</h3>
                                @if($item->brand)
                                    <p class="text-sm text-base-content/70">{{ $item->brand }}</p>
                                @endif
                                
                                <div class="flex items-center gap-2 mt-2">
                                    @if($item->is_available)
                                        <div class="badge badge-success badge-sm">Available</div>
                                    @else
                                        <div class="badge badge-warning badge-sm">In Use</div>
                                    @endif
                                </div>

                                <div class="card-actions justify-end mt-2">
                                    <a href="{{ route('equipment.show', $item) }}" 
                                       class="btn btn-primary btn-xs">
                                        View
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-public.layout>