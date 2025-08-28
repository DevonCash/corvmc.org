@props(['links' => []])

@if ($links && count($links) > 0)
    <div class="space-y-1">
        @foreach ($links as $link)
            @php
                $url = strtolower($link['url']);
                $linkIcon = match (true) {
                    str_contains($url, 'spotify') => 'tabler-brand-spotify',
                    str_contains($url, 'youtube') || str_contains($url, 'youtu.be')
                        => 'tabler-brand-youtube',
                    str_contains($url, 'instagram') => 'tabler-brand-instagram',
                    str_contains($url, 'facebook') => 'tabler-brand-facebook',
                    str_contains($url, 'twitter') || str_contains($url, 'x.com')
                        => 'tabler-brand-twitter',
                    str_contains($url, 'soundcloud') => 'tabler-brand-soundcloud',
                    str_contains($url, 'bandcamp') => 'tabler-music',
                    str_contains($url, 'music.apple.com') || str_contains($url, 'apple.com/music')
                        => 'tabler-brand-apple',
                    str_contains($url, 'music.amazon.') || str_contains($url, 'amazon.com/music')
                        => 'tabler-brand-amazon',
                    default => 'tabler-world',
                };
                $linkColor = match (true) {
                    str_contains($url, 'spotify') => 'text-success',
                    str_contains($url, 'youtube') || str_contains($url, 'youtu.be') => 'text-error',
                    str_contains($url, 'instagram') => 'text-secondary',
                    str_contains($url, 'facebook') => 'text-info',
                    str_contains($url, 'soundcloud') => 'text-warning',
                    str_contains($url, 'bandcamp') => 'text-info',
                    str_contains($url, 'music.apple.com') || str_contains($url, 'apple.com/music')
                        => 'text-base-content',
                    str_contains($url, 'music.amazon.') || str_contains($url, 'amazon.com/music')
                        => 'text-warning',
                    default => 'text-base-content/70',
                };
            @endphp
            <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer"
                class="flex items-center text-sm hover:bg-base-300 p-1 -mx-1 rounded transition-colors">
                <x-dynamic-component :component="$linkIcon" class="w-4 h-4 mr-2 {{ $linkColor }}" />
                <span class="text-base-content/80 flex-1">{{ $link['name'] }}</span>
                <x-tabler-external-link class="w-3 h-3 text-base-content/40" />
            </a>
        @endforeach
    </div>
@endif