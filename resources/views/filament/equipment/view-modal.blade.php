@php
    /** @var \App\Models\Equipment $record */
@endphp

<div class="space-y-6">
    <!-- Equipment Images -->
    @if($record->getMedia('equipment-photos')->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($record->getMedia('equipment-photos')->take(4) as $photo)
                <img src="{{ $photo->getUrl() }}"
                     alt="{{ $record->name }}"
                     class="w-full h-48 object-cover rounded-lg" />
            @endforeach
        </div>
    @else
        <div class="bg-gray-100 dark:bg-gray-800 rounded-lg h-48 flex items-center justify-center">
            <div class="text-center">
                <x-tabler-music class="w-12 h-12 text-gray-400 mx-auto mb-2" />
                <p class="text-gray-500 dark:text-gray-400">No photos available</p>
            </div>
        </div>
    @endif

    <!-- Equipment Details -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Basic Info -->
        <div class="space-y-4">
            <div>
                <h3 class="text-lg font-semibold mb-2">Equipment Details</h3>

                <dl class="space-y-2">
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Type</dt>
                        <dd class="text-sm">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                {{ $record->type === 'guitar' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : '' }}
                                {{ $record->type === 'bass' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' : '' }}
                                {{ $record->type === 'amplifier' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '' }}
                                {{ $record->type === 'microphone' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : '' }}
                                {{ $record->type === 'percussion' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : '' }}
                                {{ $record->type === 'recording' ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200' : '' }}
                                {{ $record->type === 'specialty' ? 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200' : '' }}
                            ">
                                {{ ucfirst($record->type) }}
                            </span>
                        </dd>
                    </div>

                    @if($record->brand)
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Brand</dt>
                            <dd class="text-sm">{{ $record->brand }}</dd>
                        </div>
                    @endif

                    @if($record->model)
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Model</dt>
                            <dd class="text-sm">{{ $record->model }}</dd>
                        </div>
                    @endif

                    @if($record->serial_number)
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Serial Number</dt>
                            <dd class="text-sm font-mono">{{ $record->serial_number }}</dd>
                        </div>
                    @endif

                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Condition</dt>
                        <dd class="text-sm">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                {{ $record->condition === 'excellent' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '' }}
                                {{ $record->condition === 'good' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : '' }}
                                {{ $record->condition === 'fair' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : '' }}
                                {{ in_array($record->condition, ['poor', 'needs_repair']) ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : '' }}
                            ">
                                {{ ucfirst(str_replace('_', ' ', $record->condition)) }}
                            </span>
                        </dd>
                    </div>

                    @if($record->location)
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Location</dt>
                            <dd class="text-sm">{{ $record->location }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>

        <!-- Status & Availability -->
        <div class="space-y-4">
            <div>
                <h3 class="text-lg font-semibold mb-2">Availability</h3>

                @if($record->is_available)
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3">
                        <div class="flex items-center">
                            <x-tabler-circle-check class="w-5 h-5 text-green-500 mr-2" />
                            <span class="text-green-800 dark:text-green-200 font-medium">Available for borrowing</span>
                        </div>
                    </div>
                @else
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3">
                        <div class="flex items-center">
                            <x-tabler-clock class="w-5 h-5 text-yellow-500 mr-2" />
                            <div>
                                <span class="text-yellow-800 dark:text-yellow-200 font-medium">Currently in use</span>
                                @if($record->currentLoan)
                                    <p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">
                                        Expected return: {{ $record->currentLoan->due_at->format('l, F j, Y') }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Donor Recognition -->
            @if($record->isDonated() && $record->provider)
                <div class="bg-pink-50 dark:bg-pink-900/20 border border-pink-200 dark:border-pink-800 rounded-lg p-3">
                    <div class="flex items-start">
                        <x-tabler-heart class="w-5 h-5 text-pink-500 mr-2 mt-0.5 flex-shrink-0" />
                        <div>
                            <h4 class="text-pink-800 dark:text-pink-200 font-medium">Generously Donated</h4>
                            <p class="text-sm text-pink-700 dark:text-pink-300 mt-1">
                                This equipment was donated by <strong>{{ $record->provider->name }}</strong>.
                                Thank you for supporting our community!
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Description -->
    @if($record->description)
        <div>
            <h3 class="text-lg font-semibold mb-2">Description</h3>
            <p class="text-gray-700 dark:text-gray-300">{{ $record->description }}</p>
        </div>
    @endif

    <!-- Notes -->
    @if($record->notes)
        <div>
            <h3 class="text-lg font-semibold mb-2">Additional Notes</h3>
            <p class="text-gray-700 dark:text-gray-300">{{ $record->notes }}</p>
        </div>
    @endif

    <!-- How to Request -->
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
        <h4 class="text-blue-900 dark:text-blue-100 font-medium mb-2">Interested in borrowing this equipment?</h4>
        <p class="text-sm text-blue-800 dark:text-blue-200 mb-3">
            Contact CMC staff via email or in person to request this equipment.
            {{ $record->is_available ? 'It\'s currently available for checkout!' : 'We can add you to the waitlist.' }}
        </p>
        <div class="flex gap-2">
            <a href="mailto:info@corvallismusic.org?subject=Equipment Request: {{ $record->name }}"
               class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <x-tabler-mail class="w-4 h-4 mr-1" />
                Email Request
            </a>
        </div>
    </div>
</div>
