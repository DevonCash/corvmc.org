<div class="fi-wi-stats-overview grid gap-6 ">
    <div
        class="fi-wi-stat rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 h-80 flex flex-col">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-50 dark:bg-amber-900/10">
                    <x-tabler-activity class="h-5 w-5 text-amber-600 dark:text-amber-400" />
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

            {{-- Toggle Activity Sidebar Button --}}
            <button type="button" x-data @click="$dispatch('toggle-activity-sidebar')"
                class="flex items-center justify-center w-8 h-8 text-gray-400 transition duration-75 rounded-lg outline-none hover:text-gray-500 hover:bg-gray-50 focus:bg-gray-50 focus:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400 dark:hover:bg-gray-800 dark:focus:bg-gray-800 dark:focus:text-gray-400"
                title="View All Activity">
                <x-tabler-layout-sidebar-right-expand class='size-5' />
            </button>
        </div>

        <div class="overflow-y-auto space-y-2">
            @forelse ($this->getActivities() as $activity)
                <div class="flex items-start gap-3 p-2.5 rounded-lg bg-gray-50 dark:bg-gray-800/50">
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-full {{ $activity['color'] === 'success'
                            ? 'bg-green-100 dark:bg-green-900/20'
                            : ($activity['color'] === 'info'
                                ? 'bg-blue-100 dark:bg-blue-900/20'
                                : ($activity['color'] === 'danger'
                                    ? 'bg-red-100 dark:bg-red-900/20'
                                    : 'bg-gray-100 dark:bg-gray-700')) }}">
                        @php
                            $iconComponent = str_replace('tabler:', 'tabler-', $activity['icon']);
                        @endphp
                        <x-dynamic-component :component="$iconComponent"
                            class="h-4 w-4 {{ $activity['color'] === 'success'
                                ? 'text-green-600 dark:text-green-400'
                                : ($activity['color'] === 'info'
                                    ? 'text-blue-600 dark:text-blue-400'
                                    : ($activity['color'] === 'danger'
                                        ? 'text-red-600 dark:text-red-400'
                                        : 'text-gray-600 dark:text-gray-400')) }}" />
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
