@props(['record', 'editRoute', 'type' => 'member'])

@if ($record->embeds && count($record->embeds) > 0)
    <section>
        <h2 class="text-lg font-bold text-base-content mb-6 uppercase tracking-wide border-b border-base-300 pb-2">
            Recordings
        </h2>
        <div class="space-y-6">
            @foreach ($record->embeds as $embed)
                @php
                    $embedUrl = $embed['url'] ?? $embed;
                @endphp
                <x-embed-display :url="$embedUrl" />
            @endforeach
        </div>
    </section>
@elseif($record->isOwnedBy(auth()->user()))
    <section>
        <h2 class="text-lg font-bold text-base-content mb-6 uppercase tracking-wide border-b border-base-300 pb-2">
            Recordings
        </h2>
        <div class="border-2 border-dashed border-base-300 p-8 text-center bg-base-200">
            <x-tabler-music class="w-12 h-12 text-base-content/40 mx-auto mb-3" />
            <h3 class="font-semibold text-base-content mb-2">Share Your Music</h3>
            <p class="text-base-content/70 text-sm mb-4">
                Add Bandcamp, SoundCloud, YouTube, or other embeds to showcase your work
            </p>
            <a href="{{ $editRoute }}" class="btn btn-primary btn-sm uppercase tracking-wide">
                <x-tabler-plus class="w-4 h-4 mr-2" />
                Add Content
            </a>
        </div>
    </section>
@endif
