@if($reservations->isNotEmpty())
    <div class="fi-section p-4">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-50 dark:bg-primary-900/10">
                    <x-tabler-calendar-clock class="h-4 w-4 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                        Upcoming Reservations
                    </h3>
                </div>
            </div>

            <a href="{{ route('filament.member.resources.reservations.index') }}"
               class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300">
                View all
                <x-tabler-chevron-right class="w-3 h-3" />
            </a>
        </div>

        <div class="space-y-2">
            @foreach($reservations as $reservation)
                <div class="flex items-center justify-between p-2 rounded-lg bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        {{-- Date/Time --}}
                        <div class="flex flex-col items-center justify-center px-2 py-1 bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-700 flex-shrink-0">
                            <div class="text-xs font-bold text-primary-600 dark:text-primary-400 uppercase">
                                {{ $reservation->reserved_at->format('M') }}
                            </div>
                            <div class="text-lg font-bold text-gray-900 dark:text-white leading-none">
                                {{ $reservation->reserved_at->format('j') }}
                            </div>
                        </div>

                        {{-- Details --}}
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 text-sm font-medium text-gray-900 dark:text-white">
                                <x-tabler-clock class="w-3 h-3 flex-shrink-0" />
                                <span class="truncate">
                                    {{ $reservation->reserved_at->format('g:i A') }} - {{ $reservation->reserved_until->format('g:i A') }}
                                </span>
                            </div>
                            @if($reservation->room)
                                <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                                    <x-tabler-door class="w-3 h-3 flex-shrink-0" />
                                    <span class="truncate">{{ $reservation->room }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- Duration --}}
                        <div class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400 flex-shrink-0">
                            <x-tabler-hourglass class="w-3 h-3" />
                            <span>{{ $reservation->hours_used }}h</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
