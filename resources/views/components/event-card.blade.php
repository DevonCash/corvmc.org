@props(['item'])

@php
    $event = $item;
@endphp

<div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-shadow">
    @if($event->poster_url)
    <figure>
        <img src="{{ $event->poster_thumb_url }}" alt="{{ $event->title }}" class="w-full h-48 object-cover">
    </figure>
    @else
    <figure class="bg-gradient-to-r from-primary/20 to-secondary/20 h-48 flex items-center justify-center">
        <div class="text-6xl opacity-50"><x-unicon name="tabler:music" class="size-16" /></div>
    </figure>
    @endif

    <div class="card-body">
        <h2 class="card-title">
            {{ $event->title }}
            @if($event->isFree())
            <div class="badge badge-success">FREE</div>
            @endif
        </h2>

        <div class="space-y-2 text-sm">
            <div class="flex items-center gap-2">
                <x-unicon name="tabler:calendar" class='size-4'/>
                <span>{{ $event->start_time->format('M j, Y') }}</span>
            </div>

            <div class="flex items-center gap-2">
                <x-unicon name="tabler:clock" class="size-4"/>
                <span>{{ $event->start_time->format('g:i A') }}</span>
                @if($event->doors_time)
                <span class="opacity-70">(Doors: {{ $event->doors_time->format('g:i A') }})</span>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <x-unicon name="tabler:map-pin" class='size-4'/>
                <span>{{ $event->venue_name }}</span>
            </div>

            @if(!$event->isFree())
            <div class="flex items-center gap-2">
                <x-unicon name="tabler:ticket" class="size-4"/>
                <span>{{ $event->ticket_price_display }}</span>
            </div>
            @endif
        </div>

        @if($event->description)
        <p class="text-sm opacity-70 mt-2">{{ Str::limit($event->description, 100) }}</p>
        @endif

        @if($event->performers->count() > 0)
        <div class="mt-3">
            <div class="text-xs font-semibold mb-1">Featuring:</div>
            <div class="flex flex-wrap gap-1">
                @foreach($event->performers->take(3) as $performer)
                <span class="badge badge-outline badge-sm">{{ $performer->name }}</span>
                @endforeach
                @if($event->performers->count() > 3)
                <span class="badge badge-outline badge-sm">+{{ $event->performers->count() - 3 }} more</span>
                @endif
            </div>
        </div>
        @endif

        <div class="card-actions justify-between items-center mt-4">
            <div>
                @if($event->hasTickets())
                <a href="{{ $event->ticket_url }}" target="_blank" class="btn btn-primary btn-sm">
                    Get Tickets
                </a>
                @endif
            </div>
            <a href="{{ route('events.show', $event) }}" class="btn btn-outline btn-sm">
                Details
            </a>
        </div>
    </div>
</div>