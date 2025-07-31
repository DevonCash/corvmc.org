@props([
    'links' => [],
])

<ul>
    @foreach ($links as $link)
        <li>
            <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer">
                {{ $link['label'] }}
            </a>
        </li>
    @endforeach
</ul>
