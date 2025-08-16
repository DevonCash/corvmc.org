@props(['url'])

@php
    // Auto-detect embed type from URL
    $type = 'iframe'; // default
    if (str_contains($url, 'spotify.com')) {
        $type = 'spotify';
    } elseif (str_contains($url, 'bandcamp.com')) {
        $type = 'bandcamp';
    } elseif (str_contains($url, 'soundcloud.com')) {
        $type = 'soundcloud';
    } elseif (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be') || str_contains($url, 'vimeo.com')) {
        $type = 'iframe';
    }
@endphp

<div class="relative">
    @if ($type === 'iframe')
        <div class="aspect-video rounded-lg overflow-hidden bg-white shadow-sm border border-gray-200">
            <iframe src="{{ $url }}"
                title="Embedded video"
                class="w-full h-full border-0"
                frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                referrerpolicy="strict-origin-when-cross-origin"
                loading="lazy"
                allowfullscreen>
            </iframe>
        </div>
    @elseif($type === 'spotify')
        <iframe
            src="{{ $url }}"
            width="100%"
            height="352"
            frameborder="0"
            allowtransparency="true"
            allow="encrypted-media"
            class="w-full border-0 rounded-lg">
        </iframe>
    @elseif($type === 'bandcamp')
        <iframe
            src="{{ $url }}"
            width="400"
            height="340"
            frameborder="0"
            seamless
            class="w-full border-0 rounded-lg">
        </iframe>
    @elseif($type === 'soundcloud')
        <iframe
            src="{{ $url }}"
            width="100%"
            height="166"
            scrolling="no"
            frameborder="no"
            allow="autoplay"
            class="w-full border-0 rounded-lg">
        </iframe>
    @endif
</div>