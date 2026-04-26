@php
    // Brand-derived accent colors, cycling per category.
    // Uses CSS custom properties so they respect dark mode.
    $accentColors = [
        'var(--cmc-orange)',       // #e5771e
        'var(--color-secondary)',  // CMC Blue #003b5c
        'var(--cmc-light-blue)',   // #b8dde1
        'var(--cmc-yellow)',       // #ffe28a
        'var(--color-success)',    // green
        '#f84d13',                // red-orange (header stripe)
        '#ffb500',                // goldenrod (header stripe)
        'var(--color-accent)',     // light blue variant
    ];
@endphp

<x-public.layout title="Local Resources - Corvallis Music Collective">
    <!-- Hero Section -->
    <div class="bg-secondary/10 py-16">
        <div class="container mx-auto px-4">
            <div class="text-center max-w-3xl mx-auto">
                <h1 class="text-5xl font-bold mb-6">Local Resources</h1>
                <p class="text-lg opacity-80">
                    A curated list of local businesses and services that support the Corvallis music community.
                    These are resources our members have found valuable.
                </p>
            </div>
        </div>
    </div>

    <!-- Quick Navigation -->
    @if ($lists->count() > 1)
        <div class="bg-base-200 py-4 sticky top-0 z-10">
            <div class="container mx-auto px-4">
                <div class="flex flex-wrap justify-center gap-2">
                    @foreach ($lists as $list)
                        <a href="#{{ $list->slug }}" class="btn btn-sm btn-ghost">
                            {{ $list->name }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <!-- Resource Lists -->
    <div class="py-12">
        <div class="container mx-auto px-4">
            @forelse ($lists as $list)
                <section
                    id="{{ $list->slug }}"
                    class="mb-10 last:mb-0 scroll-mt-20 max-w-4xl mx-auto border-l-4 pl-6"
                    style="border-color: {{ $accentColors[$loop->index % count($accentColors)] }}"
                >
                    <h2 class="text-2xl font-bold mb-1">{{ $list->name }}</h2>
                    @if ($list->description)
                        <p class="text-base-content/60 text-sm mb-4">{{ $list->description }}</p>
                    @else
                        <div class="mb-4"></div>
                    @endif

                    @if ($list->publishedResources->count() > 0)
                        <div class="divide-y divide-base-200">
                            @foreach ($list->publishedResources as $resource)
                                <div class="py-2 flex flex-col sm:flex-row sm:items-baseline gap-x-4 gap-y-0.5">
                                    <div class="flex-1 min-w-0">
                                        <span class="font-medium">
                                            @if ($resource->website)
                                                <a href="{{ $resource->website }}" target="_blank" rel="noopener noreferrer" class="link link-hover link-primary">
                                                    {{ $resource->name }}<x-tabler-external-link class="inline w-3.5 h-3.5 ml-0.5 opacity-50" />
                                                </a>
                                            @else
                                                {{ $resource->name }}
                                            @endif
                                        </span>
                                        @if ($resource->description)
                                            <span class="text-base-content/60 text-sm ml-1">— {{ $resource->description }}</span>
                                        @endif
                                    </div>
                                    @if ($resource->address)
                                        <span class="text-sm text-base-content/50 shrink-0 sm:text-right">{{ $resource->address }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-base-content/50 italic text-sm">No resources listed yet.</p>
                    @endif
                </section>
            @empty
                <div class="text-center py-16">
                    <x-tabler-folder-off class="w-16 h-16 mx-auto text-base-content/30 mb-4" />
                    <h2 class="text-2xl font-bold mb-2">No Resources Available</h2>
                    <p class="text-base-content/70">Check back soon for local resources and recommendations.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Suggest a Resource CTA -->
    <div class="bg-primary text-primary-content py-16">
        <div class="container mx-auto px-4">
            <div class="text-center max-w-3xl mx-auto">
                <h2 class="text-4xl font-bold mb-6">Know a Great Local Resource?</h2>
                <p class="text-lg mb-8 opacity-90">
                    Help us build this directory! If you know of a local business or service
                    that would benefit the Corvallis music community, share your recommendation.
                </p>
                @livewire('resource-suggestion-form')
            </div>
        </div>
    </div>
</x-public.layout>
