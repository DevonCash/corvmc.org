@props(['item'])

@php
    $band = $item;
@endphp

<div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-shadow">
    <figure class="bg-gradient-to-r from-primary/20 to-secondary/20 h-48 flex items-center justify-center">
        @if ($band->avatar_url)
            <img src="{{ $band->avatar_thumb_url }}" alt="{{ $band->name }}" class="w-full h-48 object-cover">
        @else
            <div class="text-6xl opacity-50">
                <x-unicon name="tabler:guitar-pick" class="size-16" />
            </div>
        @endif
    </figure>

    <div class="card-body">
        <h2 class="card-title">{{ $band->name }}</h2>

        @if ($band->hometown)
            <div class="flex items-center gap-2 text-sm opacity-70">
                <x-unicon name="tabler:map-pin" class="size-4"/>
                <span>{{ $band->hometown }}</span>
            </div>
        @endif

        @if ($band->genres->count() > 0)
            <div class="flex flex-wrap gap-1 mt-2">
                @foreach ($band->genres->take(3) as $genre)
                    <span class="badge badge-secondary badge-sm">{{ $genre->name }}</span>
                @endforeach
                @if ($band->genres->count() > 3)
                    <span class="badge badge-outline badge-sm">+{{ $band->genres->count() - 3 }}</span>
                @endif
            </div>
        @endif

        @if ($band->bio)
            <p class="text-sm mt-3">{{ Str::limit(strip_tags($band->bio), 120) }}</p>
        @endif

        <div class="card-actions justify-end mt-4">
            <a href="{{ route('bands.show', $band) }}" class="btn btn-primary btn-sm">
                View Band
            </a>
        </div>
    </div>
</div>