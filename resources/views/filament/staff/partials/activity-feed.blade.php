<div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="p-6">
        {{-- Header --}}
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-cyan-50 dark:bg-cyan-900/10">
                    <x-tabler-activity class="h-5 w-5 text-cyan-600 dark:text-cyan-400" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                        Recent Activity
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        What's happening in the community
                    </p>
                </div>
            </div>

            <a href="{{ route('filament.staff.resources.activity-log.activity-logs.index') }}" class="inline-flex items-center gap-1 text-xs font-medium text-cyan-600 hover:text-cyan-500 dark:text-cyan-400 dark:hover:text-cyan-300">
                View all
                <x-tabler-chevron-right class="w-3 h-3" />
            </a>
        </div>

        <div class="max-h-80 overflow-y-auto space-y-2">
            @forelse ($activities as $activity)
                <div class="flex items-start gap-3 p-2.5 rounded-lg bg-gray-50 dark:bg-gray-800/50">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full flex-shrink-0
                        {{ $activity['color'] === 'success' ? 'bg-green-100 dark:bg-green-900/20' : '' }}
                        {{ $activity['color'] === 'info' ? 'bg-blue-100 dark:bg-blue-900/20' : '' }}
                        {{ $activity['color'] === 'danger' ? 'bg-red-100 dark:bg-red-900/20' : '' }}
                        {{ $activity['color'] === 'gray' ? 'bg-gray-100 dark:bg-gray-700' : '' }}
                    ">
                        <x-dynamic-component
                            :component="$activity['icon']"
                            class="h-4 w-4
                                {{ $activity['color'] === 'success' ? 'text-green-600 dark:text-green-400' : '' }}
                                {{ $activity['color'] === 'info' ? 'text-blue-600 dark:text-blue-400' : '' }}
                                {{ $activity['color'] === 'danger' ? 'text-red-600 dark:text-red-400' : '' }}
                                {{ $activity['color'] === 'gray' ? 'text-gray-600 dark:text-gray-400' : '' }}
                            "
                        />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-950 dark:text-white">
                            {{ $activity['description'] }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $activity['created_at']->diffForHumans() }}
                        </p>
                    </div>
                </div>
            @empty
                <div class="text-center py-8">
                    <x-tabler-activity class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        No recent activity to show
                    </p>
                </div>
            @endforelse
        </div>
    </div>
</div>
