<x-filament-panels::page>
    <div class="space-y-8">
        {{-- My Upcoming Shifts --}}
        @php($myShifts = $this->getMyUpcomingShifts())
        @if($myShifts->isNotEmpty())
            <div>
                <h3 class="mb-4 text-xl font-semibold text-gray-900 dark:text-white">My Upcoming Shifts</h3>

                <div class="space-y-3">
                    @foreach($myShifts as $hourLog)
                        @php($shift = $hourLog->shift)
                        <x-filament::section>
                            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <h4 class="font-semibold text-gray-900 dark:text-white">
                                        {{ $shift->position->title }}
                                    </h4>
                                    @if($shift->event)
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $shift->event->title }}
                                        </p>
                                    @endif
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $shift->start_at->format('l, M j') }}
                                        &middot; {{ $shift->start_at->format('g:i A') }}–{{ $shift->end_at->format('g:i A') }}
                                    </p>
                                </div>

                                <div class="flex items-center gap-2">
                                    <x-filament::badge :color="$hourLog->status->getColor()">
                                        {{ $hourLog->status->getLabel() }}
                                    </x-filament::badge>

                                    @if($hourLog->status instanceof \CorvMC\Volunteering\States\HourLogState\Confirmed
                                        && \App\Filament\Member\Pages\VolunteerPage::isInCheckInWindow($shift))
                                        <x-filament::button
                                            wire:click="checkIn({{ $hourLog->id }})"
                                            color="success"
                                            size="sm"
                                        >
                                            Check In
                                        </x-filament::button>
                                    @elseif($hourLog->status instanceof \CorvMC\Volunteering\States\HourLogState\CheckedIn)
                                        <x-filament::button
                                            wire:click="checkOut({{ $hourLog->id }})"
                                            color="warning"
                                            size="sm"
                                        >
                                            Check Out
                                        </x-filament::button>
                                    @endif
                                </div>
                            </div>
                        </x-filament::section>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Open Shifts --}}
        <div>
            <h3 class="mb-4 text-xl font-semibold text-gray-900 dark:text-white">Open Shifts</h3>

            @forelse($this->getOpenShifts() as $groupName => $shifts)
                <div class="mb-6">
                    <h4 class="mb-2 text-lg font-medium text-gray-700 dark:text-gray-300">{{ $groupName }}</h4>

                    <div class="space-y-2">
                        @foreach($shifts as $item)
                            @php($shift = $item['shift'])
                            @php($myLog = $item['my_hour_log'])
                            <x-filament::section>
                                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                    <div>
                                        <span class="font-semibold text-gray-900 dark:text-white">
                                            {{ $shift->position->title }}
                                        </span>
                                        <span class="text-sm text-gray-500 dark:text-gray-400">
                                            &middot; {{ $shift->start_at->format('M j, g:i A') }}–{{ $shift->end_at->format('g:i A') }}
                                        </span>
                                        <span class="text-sm text-gray-500 dark:text-gray-400">
                                            &middot; {{ $item['available'] }} {{ Str::plural('spot', $item['available']) }} left
                                        </span>
                                    </div>

                                    <div>
                                        @if($myLog)
                                            <x-filament::badge :color="$myLog->status->getColor()">
                                                {{ $myLog->status->getLabel() }}
                                            </x-filament::badge>
                                        @else
                                            <x-filament::button
                                                wire:click="signUp({{ $shift->id }})"
                                                size="sm"
                                            >
                                                Sign Up
                                            </x-filament::button>
                                        @endif
                                    </div>
                                </div>
                            </x-filament::section>
                        @endforeach
                    </div>
                </div>
            @empty
                <x-filament::section>
                    <div class="py-6 text-center">
                        <x-tabler-calendar-off class="mx-auto mb-2 size-12 text-gray-400" />
                        <p class="text-gray-500 dark:text-gray-400">No open shifts right now.</p>
                    </div>
                </x-filament::section>
            @endforelse
        </div>

        {{-- Past Volunteer History --}}
        @php($history = $this->getMyHistory())
        @if($history->isNotEmpty())
            <div>
                <h3 class="mb-4 text-xl font-semibold text-gray-900 dark:text-white">My Volunteer History</h3>

                <x-filament::section>
                    <div class="overflow-x-auto">
                        <table class="w-full text-start divide-y divide-gray-200 dark:divide-white/5">
                            <thead>
                                <tr>
                                    <th class="px-3 py-3.5 text-sm font-semibold text-gray-950 dark:text-white text-start">Position</th>
                                    <th class="px-3 py-3.5 text-sm font-semibold text-gray-950 dark:text-white text-start">Event</th>
                                    <th class="px-3 py-3.5 text-sm font-semibold text-gray-950 dark:text-white text-start">Date</th>
                                    <th class="px-3 py-3.5 text-sm font-semibold text-gray-950 dark:text-white text-end">Minutes</th>
                                    <th class="px-3 py-3.5 text-sm font-semibold text-gray-950 dark:text-white text-start">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                                @foreach($history as $log)
                                    <tr>
                                        <td class="px-3 py-4 text-sm text-gray-950 dark:text-white">
                                            {{ $log->resolvePosition()?->title ?? '—' }}
                                        </td>
                                        <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {{ $log->shift?->event?->title ?? '—' }}
                                        </td>
                                        <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {{ ($log->started_at ?? $log->created_at)->format('M j, Y') }}
                                        </td>
                                        <td class="px-3 py-4 text-sm text-gray-950 dark:text-white text-end">
                                            {{ $log->minutes ?? '—' }}
                                        </td>
                                        <td class="px-3 py-4 text-sm">
                                            <x-filament::badge :color="$log->status->getColor()" size="sm">
                                                {{ $log->status->getLabel() }}
                                            </x-filament::badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            </div>
        @endif
    </div>
</x-filament-panels::page>
