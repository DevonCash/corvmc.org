@props([
    'event',
    'size' => 'auto', // auto, thumb, medium, large, optimized
    'class' => '',
    'alt' => null,
])

@php
    $media = $event->getFirstMedia('poster');
    $hasMedia = (bool) $media;

    // Get URLs for each size
    $urls = [
        'thumb' => $event->poster_thumb_url,      // 200w
        'medium' => $event->poster_url,            // 400w
        'large' => $event->poster_large_url,       // 600w
        'optimized' => $event->poster_optimized_url, // 850w
    ];

    $altText = $alt ?? "{$event->title} poster";

    // For fixed sizes, just use that size
    if ($size !== 'auto') {
        $singleUrl = $urls[$size] ?? $urls['medium'];
    }
@endphp

@if($hasMedia)
    @if($size === 'auto')
        {{-- Responsive picture element with appropriate sources --}}
        <picture>
            {{-- Large screens: use optimized (850w) --}}
            <source
                media="(min-width: 1024px)"
                srcset="{{ $urls['optimized'] }}"
            >
            {{-- Medium screens: use large (600w) --}}
            <source
                media="(min-width: 640px)"
                srcset="{{ $urls['large'] }}"
            >
            {{-- Small screens: use medium (400w) --}}
            <source
                media="(min-width: 320px)"
                srcset="{{ $urls['medium'] }}"
            >
            {{-- Fallback for very small screens or no picture support --}}
            <img
                src="{{ $urls['medium'] }}"
                alt="{{ $altText }}"
                {{ $attributes->merge(['class' => $class]) }}
                loading="lazy"
            >
        </picture>
    @else
        {{-- Fixed size requested --}}
        <img
            src="{{ $singleUrl }}"
            alt="{{ $altText }}"
            {{ $attributes->merge(['class' => $class]) }}
            loading="lazy"
        >
    @endif
@else
    {{-- No poster uploaded - render fallback slot or default --}}
    @if($slot->isNotEmpty())
        {{ $slot }}
    @else
        <div {{ $attributes->merge(['class' => "bg-secondary/20 flex items-center justify-center $class"]) }}>
            <div class="text-center opacity-30 p-4">
                <x-icon name="tabler-music" class="size-16 mx-auto mb-2" />
                <p class="text-sm font-bold">{{ $event->title }}</p>
            </div>
        </div>
    @endif
@endif
