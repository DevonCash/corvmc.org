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
    @if ($lists->count() > 0)
        <div class="bg-base-200 py-4 sticky top-0 z-10">
            <div class="container mx-auto px-4">
                <div class="flex flex-wrap justify-center gap-2">
                    @foreach ($lists as $list)
                        <a href="#{{ $list->slug }}" class="btn btn-sm btn-ghost">
                            {{ $list->name }}
                        </a>
                    @endforeach
                    <a href="#suggest" class="btn btn-sm btn-primary">
                        <x-tabler-plus class="w-4 h-4" />
                        Suggest
                    </a>
                </div>
            </div>
        </div>
    @endif

    <!-- Resource Lists -->
    <div class="py-16">
        <div class="container mx-auto px-4">
            @forelse ($lists as $list)
                <section id="{{ $list->slug }}" class="mb-16 last:mb-0 scroll-mt-20">
                    <div class="max-w-4xl mx-auto">
                        <h2 class="text-3xl font-bold mb-4">{{ $list->name }}</h2>
                        @if ($list->description)
                            <p class="text-base-content/70 mb-8">{{ $list->description }}</p>
                        @endif

                        @if ($list->publishedResources->count() > 0)
                            <div class="grid gap-4 md:grid-cols-2">
                                @foreach ($list->publishedResources as $resource)
                                    <div class="card bg-base-100 shadow-md hover:shadow-lg transition-shadow">
                                        <div class="card-body">
                                            <h3 class="card-title">
                                                @if ($resource->website)
                                                    <a href="{{ $resource->website }}" target="_blank" rel="noopener noreferrer" class="link link-primary hover:link-hover">
                                                        {{ $resource->name }}
                                                        <x-tabler-external-link class="inline-block w-4 h-4 ml-1" />
                                                    </a>
                                                @else
                                                    {{ $resource->name }}
                                                @endif
                                            </h3>

                                            @if ($resource->description)
                                                <p class="text-base-content/70">{{ $resource->description }}</p>
                                            @endif

                                            <div class="mt-4 space-y-2 text-sm">
                                                @if ($resource->address)
                                                    <div class="flex items-start gap-2">
                                                        <x-tabler-map-pin class="w-4 h-4 mt-0.5 text-base-content/50 shrink-0" />
                                                        <span>{{ $resource->address }}</span>
                                                    </div>
                                                @endif

                                                @if ($resource->contact_name)
                                                    <div class="flex items-center gap-2">
                                                        <x-tabler-user class="w-4 h-4 text-base-content/50 shrink-0" />
                                                        <span>{{ $resource->contact_name }}</span>
                                                    </div>
                                                @endif

                                                @if ($resource->contact_email)
                                                    <div class="flex items-center gap-2">
                                                        <x-tabler-mail class="w-4 h-4 text-base-content/50 shrink-0" />
                                                        <a href="mailto:{{ $resource->contact_email }}" class="link link-hover">{{ $resource->contact_email }}</a>
                                                    </div>
                                                @endif

                                                @if ($resource->contact_phone)
                                                    <div class="flex items-center gap-2">
                                                        <x-tabler-phone class="w-4 h-4 text-base-content/50 shrink-0" />
                                                        <a href="tel:{{ $resource->contact_phone }}" class="link link-hover">{{ $resource->contact_phone }}</a>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-base-content/50 italic">No resources listed yet.</p>
                        @endif
                    </div>
                </section>

                @if (!$loop->last)
                    <div class="divider max-w-4xl mx-auto"></div>
                @endif
            @empty
                <div class="text-center py-16">
                    <x-tabler-folder-off class="w-16 h-16 mx-auto text-base-content/30 mb-4" />
                    <h2 class="text-2xl font-bold mb-2">No Resources Available</h2>
                    <p class="text-base-content/70">Check back soon for local resources and recommendations.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Suggest a Resource Form -->
    <div id="suggest" class="bg-base-200 py-16 scroll-mt-20">
        <div class="container mx-auto px-4">
            <div class="max-w-2xl mx-auto">
                <div class="text-center mb-8">
                    <h2 class="text-4xl font-bold mb-4">Suggest a Resource</h2>
                    <p class="text-lg text-base-content/70">
                        Know a great local business or service that would benefit the Corvallis music community?
                        Let us know and we'll consider adding it to our directory.
                    </p>
                </div>

                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        @livewire('resource-suggestion-form')
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-public.layout>
