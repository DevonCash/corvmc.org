<x-filament-panels::page>
    <div class="space-y-6">
        @if($claimableBand)
            <!-- Existing Band Information -->
            <div class="bg-warning-50 dark:bg-warning-950 rounded-lg border border-warning-200 dark:border-warning-800 p-6">
                <div class="flex items-start space-x-4">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-warning-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 15.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-medium text-warning-800 dark:text-warning-200">
                            Band Name Already Exists
                        </h3>
                        <p class="mt-2 text-sm text-warning-700 dark:text-warning-300">
                            A band named "<strong>{{ $claimableBand->name }}</strong>" already exists in our system but has no owner. 
                            You can claim ownership of this existing band profile instead of creating a duplicate.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Existing Band Details -->
            <div class="bg-white dark:bg-gray-900 shadow rounded-lg">
                <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        Existing Band Information
                    </h3>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Band Name</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $claimableBand->name }}</dd>
                    </div>
                    
                    @if($claimableBand->hometown)
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Location</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $claimableBand->hometown }}</dd>
                        </div>
                    @endif
                    
                    @if($claimableBand->bio)
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Biography</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{!! nl2br(e(Str::limit($claimableBand->bio, 200))) !!}</dd>
                        </div>
                    @endif
                    
                    @if($claimableBand->genres && $claimableBand->genres->count() > 0)
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Genres</dt>
                            <dd class="mt-1">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($claimableBand->genres as $genre)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200">
                                            {{ $genre->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </dd>
                        </div>
                    @endif
                    
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                Guest Band (No Owner)
                            </span>
                        </dd>
                    </div>
                </div>
            </div>

            <!-- Your New Data Preview -->
            @if($originalData)
                <div class="bg-white dark:bg-gray-900 shadow rounded-lg">
                    <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                            Your Additional Information
                        </h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            This information from your form will be merged with the existing band profile if you claim it.
                        </p>
                    </div>
                    <div class="px-6 py-5 space-y-4">
                        @foreach($originalData as $key => $value)
                            @if($key !== 'name' && $value)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {{ ucfirst(str_replace('_', ' ', $key)) }}
                                    </dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                        @if(is_array($value))
                                            {{ implode(', ', $value) }}
                                        @else
                                            {{ $value }}
                                        @endif
                                    </dd>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Similar Bands -->
            @if($similarBands && $similarBands->count() > 1)
                <div class="bg-blue-50 dark:bg-blue-950 rounded-lg border border-blue-200 dark:border-blue-800 p-6">
                    <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200 mb-3">
                        Other Similar Band Names in System
                    </h4>
                    <div class="text-xs text-blue-700 dark:text-blue-300 space-y-1">
                        @foreach($similarBands as $id => $name)
                            @if($name !== $claimableBand->name)
                                <div>• {{ $name }}</div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Action Instructions -->
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-6">
                <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">
                    What happens when you claim this band?
                </h4>
                <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-2 list-disc list-inside">
                    <li>You'll become the owner and administrator of this band profile</li>
                    <li>Your additional information will be merged with the existing profile</li>
                    <li>You can edit and manage all aspects of the band profile</li>
                    <li>The band status will change from "Guest Band" to "Active"</li>
                    <li>You can invite other members to join the band</li>
                </ul>
            </div>
        @endif
    </div>
</x-filament-panels::page>