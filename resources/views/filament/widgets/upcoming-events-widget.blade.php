<x-filament-widgets::widget class="fi-upcoming-events-widget">
    <x-filament::section compact>
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-50 dark:bg-primary-900/10">
                    <x-tabler-calendar-event class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                        Upcoming Events
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Next events in the community
                    </p>
                </div>
            </div>

            @if ($this->getUpcomingEvents()->isNotEmpty())
                <a href="{{ route('events.index') }}"
                   class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300">
                    View all
                    <x-tabler-chevron-right class="w-4 h-4" />
                </a>
            @endif
        </div>

        {{-- Horizontal Scrolling Poster Cards --}}
        <div class="relative">
            @forelse ($this->getUpcomingEvents() as $event)
                @if($loop->first)
                    <div class="flex gap-4 overflow-x-auto pb-4 scrollbar-hide">
                @endif

                {{-- Poster Card --}}
                <div class="flex-shrink-0 group">
                    <div class="block w-48 bg-white dark:bg-gray-800 rounded-lg shadow-sm hover:shadow-md transition-all duration-200 border border-gray-200 dark:border-gray-700 hover:border-primary-300 dark:hover:border-primary-600 overflow-hidden cursor-pointer"
                         onclick="window.open('{{ $event['public_url'] }}', '_blank')">

                        {{-- Poster Image --}}
                        <div class="relative aspect-[3/4] bg-primary">
                            @if($event['poster_thumb_url'] || $event['poster_url'])
                                <img src="{{ $event['poster_thumb_url'] ?: $event['poster_url'] }}"
                                     alt="{{ $event['title'] }} poster "
                                     class="w-full h-full object-cover">
                            @else
                                {{-- Fallback poster design --}}
                                <div class="absolute inset-0 flex flex-col items-center justify-center p-4">
                                    <x-tabler-calendar-event class="w-12 h-12 text-primary-600 dark:text-primary-400 mb-3" />
                                    <h4 class="text-sm font-bold text-center text-primary-900 dark:text-primary-100 leading-tight">
                                        {{ $event['title'] ?? 'Untitled Event' }}
                                    </h4>
                                </div>
                            @endif

                            {{-- Price Badge --}}
                            <div class="absolute top-2 right-2">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium backdrop-blur-sm {{
                                    $event['is_free'] ? 'bg-green-500/90 text-white' : 'bg-amber-500/90 text-white'
                                }}">
                                    {{ $event['ticket_price_display'] }}
                                </span>
                            </div>

                            {{-- Quick Actions Overlay --}}
                            <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center p-4">
                                <div class="flex flex-col gap-3 items-center justify-center">
                                    {{-- Tickets button (if available) --}}
                                    @if($event['ticket_url'])
                                        <a href="{{ $event['ticket_url'] }}"
                                           target="_blank"
                                           onclick="event.stopPropagation()"
                                           class="flex items-center justify-center w-12 h-12 bg-primary-600 text-white rounded-full hover:bg-primary-700 transition-colors shadow-lg">
                                            <x-tabler-ticket class="w-5 h-5" />
                                        </a>
                                    @endif

                                    {{-- View Details button (always available) --}}
                                    <a href="{{ $event['public_url'] }}"
                                       target="_blank"
                                       class="flex items-center justify-center w-12 h-12 bg-white/20 backdrop-blur-sm text-white rounded-full hover:bg-white/30 transition-colors shadow-lg">
                                        <x-tabler-eye class="w-5 h-5" />
                                    </a>
                                </div>
                            </div>
                        </div>

                        {{-- Event Info --}}
                        <div class="p-3">
                            <h4 class="font-semibold text-sm text-gray-900 dark:text-white line-clamp-1 leading-tight mb-2 truncate text-ellipsis">
                                {{ $event['title'] ?? 'Untitled Event' }}
                            </h4>

                            {{-- Date --}}
                            <div class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400 mb-1">
                                <x-tabler-calendar class="w-3 h-3" />
                                <span class="truncate">{{ $event['start_time']->format('M j, Y') }}</span>
                            </div>

                            {{-- Time --}}
                            <div class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400 mb-1">
                                <x-tabler-clock class="w-3 h-3" />
                                <span class="truncate">{{ $event['start_time']->format('g:i A') }}</span>
                            </div>

                            {{-- Venue --}}
                            <div class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400">
                                <x-tabler-map-pin class="w-3 h-3" />
                                <span class="truncate">{{ $event['venue_name'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                @if($loop->last)
                    </div>
                @endif
            @empty
            {{-- Empty State --}}
            <div class="text-center py-12">
                <div class="w-32 h-40 mx-auto bg-gray-100 dark:bg-gray-800 rounded-lg flex items-center justify-center mb-4">
                    <x-tabler-calendar-off class="w-16 h-16 text-gray-400 dark:text-gray-600" />
                </div>
                <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-2">No upcoming events</h4>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                    No events are currently scheduled in the community
                </p>
                @if(auth()->user()?->can('create events'))
                    <a href="{{ route('filament.staff.resources.events.create') }}"
                       class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors">
                        <x-tabler-plus class="w-4 h-4" />
                        Create Event
                    </a>
                @endif
            </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
