<div class="fi-wi-stats-overview grid gap-6">
    <div class="fi-wi-stat rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex items-center gap-3 mb-4">
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

        <div class="space-y-4">
            @forelse ($this->getUpcomingEvents() as $event)
                <div class="flex gap-4 p-4 rounded-lg bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700">
                    {{-- Event Poster --}}
                    @if($event['poster_thumb_url'])
                        <div class="flex-shrink-0">
                            <img src="{{ $event['poster_thumb_url'] }}" 
                                 alt="{{ $event['title'] }} poster"
                                 class="w-16 h-16 rounded-lg object-cover">
                        </div>
                    @else
                        <div class="flex-shrink-0 w-16 h-16 bg-primary-100 dark:bg-primary-900/20 rounded-lg flex items-center justify-center">
                            <x-tabler-music class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                        </div>
                    @endif

                    {{-- Event Details --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h4 class="text-sm font-semibold text-gray-950 dark:text-white truncate">
                                    {{ $event['title'] }}
                                </h4>
                                
                                {{-- Date & Time --}}
                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                    <x-tabler-clock class="inline h-3 w-3 mr-1" />
                                    {{ $event['date_range'] }}
                                </p>
                                
                                {{-- Venue --}}
                                <p class="text-xs text-gray-600 dark:text-gray-400">
                                    <x-tabler-map-pin class="inline h-3 w-3 mr-1" />
                                    {{ $event['venue_name'] }}
                                </p>

                                {{-- Performers --}}
                                @if($event['performers']->count() > 0)
                                    <div class="mt-2">
                                        <p class="text-xs text-gray-500 dark:text-gray-500">
                                            Performers:
                                        </p>
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            @foreach($event['performers']->take(3) as $performer)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-primary-100 text-primary-800 dark:bg-primary-900/20 dark:text-primary-200">
                                                    {{ $performer['name'] }}
                                                </span>
                                            @endforeach
                                            @if($event['performers']->count() > 3)
                                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                                    +{{ $event['performers']->count() - 3 }} more
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Price/Tickets --}}
                            <div class="text-right ml-2">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ 
                                    $event['is_free'] ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-200' : 
                                    'bg-amber-100 text-amber-800 dark:bg-amber-900/20 dark:text-amber-200' 
                                }}">
                                    {{ $event['ticket_price_display'] }}
                                </span>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex items-center gap-2 mt-3">
                            @if($event['ticket_url'])
                                <a href="{{ $event['ticket_url'] }}" 
                                   target="_blank"
                                   class="inline-flex items-center px-2 py-1 text-xs font-medium text-primary-700 bg-primary-50 rounded-md hover:bg-primary-100 dark:text-primary-200 dark:bg-primary-900/20 dark:hover:bg-primary-900/30">
                                    <x-tabler-ticket class="h-3 w-3 mr-1" />
                                    Get Tickets
                                </a>
                            @endif
                            
                            <a href="{{ $event['public_url'] }}" 
                               target="_blank"
                               class="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 dark:text-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600">
                                <x-tabler-eye class="h-3 w-3 mr-1" />
                                View Details
                            </a>

                            @if($event['edit_url'])
                                <a href="{{ $event['edit_url'] }}" 
                                   class="inline-flex items-center px-2 py-1 text-xs font-medium text-amber-700 bg-amber-50 rounded-md hover:bg-amber-100 dark:text-amber-200 dark:bg-amber-900/20 dark:hover:bg-amber-900/30">
                                    <x-tabler-edit class="h-3 w-3 mr-1" />
                                    Edit
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-8">
                    <x-tabler-calendar-off class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        No upcoming events scheduled
                    </p>
                    @if(auth()->user()?->can('create productions'))
                        <a href="{{ route('filament.member.resources.productions.create') }}" 
                           class="mt-2 inline-flex items-center text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">
                            <x-tabler-plus class="h-4 w-4 mr-1" />
                            Create Event
                        </a>
                    @endif
                </div>
            @endforelse
        </div>

        @if ($this->getUpcomingEvents()->isNotEmpty())
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="text-center">
                    <a href="{{ route('filament.member.resources.productions.index') }}" 
                       class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300">
                        View all events
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>