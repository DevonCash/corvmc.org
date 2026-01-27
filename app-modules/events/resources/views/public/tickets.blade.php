<x-public.layout :title="'Tickets: ' . $event->title . ' | Corvallis Music Collective'">
    <div class="container mx-auto px-4 py-8 max-w-2xl">
        {{-- Event Summary --}}
        <div class="card bg-base-100 shadow-lg mb-8">
            <div class="card-body">
                <h1 class="card-title text-2xl">{{ $event->title }}</h1>

                <div class="flex flex-wrap gap-4 text-sm opacity-70 mt-2">
                    <div class="flex items-center gap-2">
                        <x-tabler-calendar class="size-5" />
                        {{ $event->start_datetime->format('l, F j, Y') }}
                    </div>
                    <div class="flex items-center gap-2">
                        <x-tabler-clock class="size-5" />
                        {{ $event->start_datetime->format('g:i A') }}
                    </div>
                    @if ($event->venue_name)
                        <div class="flex items-center gap-2">
                            <x-tabler-map-pin class="size-5" />
                            {{ $event->venue_name }}
                        </div>
                    @endif
                </div>

                <a href="{{ route('events.show', $event) }}" class="link link-primary text-sm mt-2">
                    View event details
                </a>
            </div>
        </div>

        {{-- Sold Out Message --}}
        @if ($event->isSoldOut())
            <div class="alert alert-warning mb-8">
                <x-tabler-ticket-off class="size-6" />
                <span>This event is sold out.</span>
            </div>
        @else
            {{-- Ticket Purchase Widget --}}
            <livewire:ticket-purchase-widget :event="$event" />
        @endif
    </div>
</x-public.layout>
