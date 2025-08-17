@props(['item'])

@php
    $event = $item;
@endphp

<a href="{{ route('events.show', $event) }}" class='@container max-w-sm border-5 border-base-200 shadow-sm transition-transform duration-300 hover:rotate-3 hover:z-10 block'
    style="transform: rotateZ({{ rand(-1, 1) }}deg); transform-origin: top center;">
    <figure class='bg-accent aspect-[8.5/11] relative flex items-center justify-center '>
        @if ($event->poster_url)
            <img src="{{ $event->poster_url }}" alt="{{ $event->title }}" class="w-full h-full object-cover">
        @endif
    </figure>

    <div class="p-3 flex items-center gap-4 bg-base-200 @max-xs:flex-col">
        <x-logo :soundLines="false" class='h-12 -mr-2 @max-xs:hidden' />
        <hgroup class='w-full'>
            <h2 class="card-title text-sm mb-2">
                {{ $event->title }}
                @if ($event->isFree())
                    <div class="badge badge-success badge-xs">FREE</div>
                @endif
            </h2>

            <div class="text-xs opacity-70">
                {{ $event->start_time->format('M j, g:i A') }}
            </div>
        </hgroup>

    </div>
</a>
