<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filters --}}
        <x-filament::section>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From</label>
                    <input type="date" id="start_date" wire:model.live="start_date"
                        class="w-full rounded-lg border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                </div>
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To</label>
                    <input type="date" id="end_date" wire:model.live="end_date"
                        class="w-full rounded-lg border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                </div>
                <div>
                    <label for="tag_filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Filter by Tag</label>
                    <input type="text" id="tag_filter" wire:model.live.debounce.500ms="tag_filter" placeholder="e.g. community-service"
                        class="w-full rounded-lg border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm placeholder-gray-400">
                </div>
            </div>
        </x-filament::section>

        {{-- Stats --}}
        @php($stats = $this->getStats())
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-primary-600 dark:text-primary-400">
                        {{ $stats['total_hours'] }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total Hours</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-primary-600 dark:text-primary-400">
                        {{ $stats['unique_volunteers'] }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Unique Volunteers</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-primary-600 dark:text-primary-400">
                        {{ $stats['shifts_staffed'] }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Shifts Staffed</div>
                </div>
            </x-filament::section>
        </div>

        {{-- Hours by Volunteer --}}
        <x-filament::section heading="Hours by Volunteer">
            <div class="overflow-x-auto">
                <table class="w-full text-start divide-y divide-gray-200 dark:divide-white/5">
                    <thead>
                        <tr>
                            <th class="px-3 py-3.5 text-sm font-semibold text-gray-950 dark:text-white text-start">Volunteer</th>
                            <th class="px-3 py-3.5 text-sm font-semibold text-gray-950 dark:text-white text-end">Hours</th>
                            <th class="px-3 py-3.5 text-sm font-semibold text-gray-950 dark:text-white text-end">Sessions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @forelse($this->getHoursByVolunteer() as $row)
                            <tr>
                                <td class="px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $row['name'] }}</td>
                                <td class="px-3 py-4 text-sm text-gray-950 dark:text-white text-end">{{ $row['total_hours'] }}</td>
                                <td class="px-3 py-4 text-sm text-gray-950 dark:text-white text-end">{{ $row['sessions'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No volunteer hours found for this period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- Hours by Position --}}
        <x-filament::section heading="Hours by Position">
            <div class="overflow-x-auto">
                <table class="w-full text-start divide-y divide-gray-200 dark:divide-white/5">
                    <thead>
                        <tr>
                            <th class="px-3 py-3.5 text-sm font-semibold text-gray-950 dark:text-white text-start">Position</th>
                            <th class="px-3 py-3.5 text-sm font-semibold text-gray-950 dark:text-white text-end">Hours</th>
                            <th class="px-3 py-3.5 text-sm font-semibold text-gray-950 dark:text-white text-end">Volunteers</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @forelse($this->getHoursByPosition() as $row)
                            <tr>
                                <td class="px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $row['title'] }}</td>
                                <td class="px-3 py-4 text-sm text-gray-950 dark:text-white text-end">{{ $row['total_hours'] }}</td>
                                <td class="px-3 py-4 text-sm text-gray-950 dark:text-white text-end">{{ $row['volunteer_count'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No volunteer hours found for this period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
