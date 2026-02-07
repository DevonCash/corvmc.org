<x-public.layout :title="$event->title . ' | Corvallis Music Collective'">
    @if ($isPreview ?? false)
        <div class="bg-info text-info-content">
            <div class="container mx-auto px-4 py-3">
                <div class="flex items-center justify-between gap-4 flex-wrap">
                    <div class="flex items-center gap-3">
                        <x-tabler-eye class="size-5 flex-shrink-0" />
                        <div>
                            <span class="font-semibold">Preview Mode</span>
                            <span class="hidden sm:inline"> – This event is not yet published. Only you can see this page.</span>
                            <span class="sm:hidden"> – Not yet published</span>
                        </div>
                    </div>
                    <a href="/staff/events/{{ $event->id }}/edit" class="btn btn-sm btn-ghost">
                        <x-tabler-edit class="size-4" />
                        Edit
                    </a>
                </div>
            </div>
        </div>
    @endif

    <!-- Hero Section: Full Height Poster + Event Details -->
    <div class="flex flex-col items-center lg:grid grid-cols-2 gap-x-8 justify-center md:mx-auto p-8 max-w-screen"
        style="grid-template-columns: min(calc(90vh * 8.5/11), calc(100vw - var(--container-xs) - 12rem)) min-content; place-content: center;">
        <!-- Poster Section - Takes most of the height -->
        <div
            class="max-h-[75vh] h-auto  sm:h-[80vh] w-full sm:w-auto aspect-[8.5/11] col-span-2 md:col-span-1 relative lg:ml-auto">


            <div class="bg-white p-6 rounded-lg transform transition-transform duration-500 h-full mx-auto">
                <x-events::poster :event="$event" class="w-full h-full object-cover rounded">
                    {{-- Fallback when no poster --}}
                    <div class="bg-secondary/20 rounded-lg flex items-center justify-center h-full w-full">
                        <div class="text-center opacity-30">
                            <x-icon name="tabler-music" class="size-32 mx-auto mb-6" />
                            <p class="text-2xl font-bold">{{ $event->title }}</p>
                        </div>
                    </div>
                </x-events::poster>
            </div>
        </div>
        <div class='grow flex flex-col gap-6 p-8 min-w-sm lg:border-l-2'>
            <div class="hidden sm:flex lg:hidden flex-col gap-4 justify-center absolute left-full p-4">
                <a href="https://facebook.com/sharer/sharer.php?u={{ urlencode(request()->url()) }}" target="_blank"
                    class="btn btn-outline btn-circle btn-lg">
                    <x-icon name="tabler-brand-facebook" class="size-6" />
                </a>
                <a href="https://twitter.com/intent/tweet?url={{ urlencode(request()->url()) }}&text={{ urlencode($event->title) }}"
                    target="_blank" class="btn btn-outline btn-circle btn-lg">
                    <x-icon name="tabler-brand-x" class="size-6" />
                </a>
                <button onclick="navigator.share({title: '{{ $event->title }}', url: '{{ request()->url() }}'})"
                    class="btn btn-outline btn-circle btn-lg">
                    <x-icon name="tabler-share" class="size-6" />
                </button>
            </div>
            @if ($event->ticket_url)
                <div class="border-t border-base-300 order-last pt-4">
                    <a href="{{ $event->ticket_url }}" @if (!$event->hasNativeTicketing()) target="_blank" @endif
                        class="btn btn-primary btn-lg w-full transform hover:scale-105 transition-all duration-300">
                        <x-icon name="tabler-ticket" class="size-6" />
                        Get Tickets
                    </a>
                </div>
            @endif

            <!-- Event Details Card - Aligned to poster height -->
            <div class="card w-full bg-base-100 whitespace-nowrap">
                <h1 class="card-title sm:text-2xl lg:text-3xl">{{ $event->title }}</h1>
                <!-- Event Info Grid -->
                <div class="flex flex-col grow grid-cols-3 sm:grid lg:flex gap-4 whitespace-nowrap p-4">
                    <div class="flex flex-wrap gap-2 items-center justify-center">
                        <x-icon name="tabler-calendar" class="size-8 text-primary" />
                        <div class='grow'>
                            <div class="font-semibold text-xl">
                                {{ $event->start_datetime->format('M j, Y') }}</div>
                            <div class="text-base opacity-70">
                                {{ $event->start_datetime->format('l') }}</div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 items-center justify-center">
                        <x-icon name="tabler-clock" class="size-8 text-primary " />
                        <div class='grow'>
                            <div class="font-semibold text-xl">
                                {{ $event->start_datetime->format('g:i A') }}</div>
                            @if ($event->doors_datetime)
                                <div class="text-base opacity-70">Doors:
                                    {{ $event->doors_datetime->format('g:i A') }}</div>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 items-center justify-center">
                        <x-icon name="tabler-ticket" class="size-8 text-primary flex-shrink-0" />
                        <div class='grow'>
                            <div class="font-semibold text-xl text-primary">
                                {{ $event->ticket_price_display }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Prominent Ticket Button at bottom of card -->
        </div>
    </div>
    <div class="space-y-8 container mx-auto p-4">

        <!-- Event Description -->
        @if ($event->description)
            <h2 class="font-bold text-2xl mb-4">About This Event</h2>
            <div class="prose max-w-none">
                {!! $event->description !!}
            </div>
        @endif

        <!-- Performers -->
        @if ($event->performers && $event->performers->count() > 0)
            <h2 class="font-bold text-2xl mb-6">Featured Artists</h2>
            <div
                class="grid grid-cols-1
                     @if ($event->performers->count() == 4) sm:grid-cols-2 gap-6
                     @else sm:grid-cols-2 md:grid-cols-3 gap-6 @endif">
                @foreach ($event->performers as $performer)
                    <x-events::performer-card :performer="$performer" />
                @endforeach
            </div>
        @endif



        <!-- Related Events -->
        @php
            $relatedEvents = \CorvMC\Events\Models\Event::publishedUpcoming()
                ->where('id', '!=', $event->id)
                ->limit(3)
                ->get();
        @endphp

        @if ($relatedEvents->count() > 0)
            <h2 class="font-bold text-2xl mb-6">More Upcoming Events</h2>
            <div class="carousel carousel-center max-w-full p-4 space-x-4 rounded-box mb-8">
                @foreach ($relatedEvents as $relatedEvent)
                    <div class="carousel-item">
                        <a href="{{ route('events.show', $relatedEvent) }}"
                            class="block hover:scale-105 transition-all">
                            <div class="relative group">
                                <x-events::poster
                                    :event="$relatedEvent"
                                    size="medium"
                                    class="h-64 w-auto aspect-[8.5/11] object-cover rounded-lg shadow-lg group-hover:shadow-xl transition-shadow duration-300"
                                >
                                    <div class="h-64 w-40 bg-secondary/20 rounded-lg shadow-lg group-hover:shadow-xl transition-shadow duration-300 flex items-center justify-center">
                                        <div class="text-center opacity-50">
                                            <x-icon name="tabler-music" class="size-16 mx-auto mb-2" />
                                            <p class="text-sm font-bold px-2 text-center">{{ $relatedEvent->title }}</p>
                                        </div>
                                    </div>
                                </x-events::poster>
                                <div class="absolute bottom-0 left-0 right-0 bg-black/70 text-white p-3 rounded-b-lg">
                                    <h3 class="font-bold text-sm truncate">{{ $relatedEvent->title }}</h3>
                                    <p class="text-xs opacity-90">{{ $relatedEvent->start_datetime->format('M j, Y') }}
                                    </p>
                                    @if ($relatedEvent->venue_name)
                                        <p class="text-xs opacity-75 truncate">{{ $relatedEvent->venue_name }}</p>
                                    @endif
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
            <div class="text-center">
                <a href="{{ route('events.index') }}" class="btn btn-outline btn-lg">
                    View All Events
                </a>
            </div>
        @endif
    </div>
</x-public.layout>
