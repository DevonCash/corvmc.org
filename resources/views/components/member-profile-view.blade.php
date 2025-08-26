@props(['record', 'showEditButton' => false])

{{-- Concert Program Layout - mimics a folded program booklet --}}
<div class="max-w-5xl mx-auto">

    {{-- Program Cover/Header --}}
    <div class="bg-base-200 border-b-2 border-base-300 px-8 py-6">
        <div class="text-center mb-6">
            <div class="inline-block">
                <div class="text-xs uppercase tracking-widest text-base-content/60 mb-1">
                    @if ($record->hasFlag('sponsor'))
                        Sponsor
                    @elseif($record->hasFlag('sustaining_member'))
                        Sustaining Member
                    @else
                        Member
                    @endif
                    since {{ $record->created_at->format('Y') }}
                </div>
                <div class="w-16 h-0.5 bg-primary mx-auto"></div>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row items-center gap-6">
            {{-- Artist Photo --}}
            <div class="relative">
                <img src="{{ $record->avatar_url }}" alt="{{ $record->user->name }}"
                    class="w-32 h-32 object-cover border-4 border-base-100"
                    style="clip-path: polygon(0 0, 100% 0, 95% 100%, 5% 100%);">
                @if ($record->visibility === 'private')
                    <div class="absolute -top-2 -right-2">
                        <div class="bg-error text-error-content px-2 py-1 text-xs font-bold flex items-center">
                            <x-tabler-lock class="w-3 h-3 mr-1" />
                            PRIVATE
                        </div>
                    </div>
                @endif
            </div>

            {{-- Artist Details --}}
            <div class="text-center lg:text-left flex-1">
                <h1 class="text-4xl font-bold text-base-content mb-2 tracking-tight">
                    {{ $record->user->name }}
                </h1>
                @if ($record->user->pronouns)
                    <div class="text-lg text-base-content/70 mb-2">({{ $record->user->pronouns }})</div>
                @endif

                @if ($record->hometown)
                    <div class="flex items-center justify-center lg:justify-start text-base-content/70 mb-4">
                        <x-tabler-map-pin class="w-4 h-4 mr-1" />
                        <span>{{ $record->hometown }}</span>
                    </div>
                @endif

                {{-- Performance Status Badges --}}
                @php $activeFlags = $record->getActiveFlagsWithLabels(); @endphp
                @if (count($activeFlags) > 0)
                    <div class="flex flex-wrap gap-2 justify-center lg:justify-start">
                        @foreach ($activeFlags as $flag => $label)
                            @php
                                $flagStyle = match ($flag) {
                                    'open_to_collaboration' => 'badge-info',
                                    'available_for_hire' => 'badge-success',
                                    'looking_for_band' => 'badge-secondary',
                                    'music_teacher' => 'badge-warning',
                                    'sponsor' => 'badge-accent',
                                    'sustaining_member' => 'badge-primary',
                                    default => 'badge-neutral',
                                };
                            @endphp
                            <span class="badge {{ $flagStyle }} badge-sm font-bold uppercase tracking-wide">
                                {{ $label }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Program Content - Two Column Layout like program booklet --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-0 bg-base-100">

        {{-- Main Performance Details --}}
        <div class="lg:col-span-2 px-8 py-6 space-y-8">

            {{-- Program Notes / Bio --}}
            @if ($record->bio)
                <section>
                    <h2
                        class="text-lg font-bold text-base-content mb-4 uppercase tracking-wide border-b border-base-300 pb-2">
                        Program Notes
                    </h2>
                    <div class="prose max-w-none text-base-content/80 leading-relaxed">
                        {!! $record->bio !!}
                    </div>
                </section>
            @endif

            {{-- Musical Repertoire --}}
            @if (count($record->skills) > 0 || count($record->genres) > 0 || count($record->influences) > 0)
                <section>
                    <h2
                        class="text-lg font-bold text-base-content mb-6 uppercase tracking-wide border-b border-base-300 pb-2">
                        Musical Repertoire
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        {{-- Instruments & Skills --}}
                        @if (count($record->skills) > 0)
                            <div>
                                <h3 class="font-semibold text-base-content/90 mb-3 flex items-center">
                                    <x-tabler-tools class="w-4 h-4 mr-2" />
                                    Instruments & Skills
                                </h3>
                                <div class="space-y-1 text-sm text-base-content/70">
                                    @foreach ($record->skills as $skill)
                                        @if ($showEditButton)
                                            <a href="{{ route('filament.member.resources.directory.index', ['tableFilters' => ['skills' => ['values' => [$skill]]]]) }}"
                                                target="_blank" class="block hover:text-primary hover:underline">
                                                • {{ $skill }}
                                            </a>
                                        @else
                                            <div>• {{ $skill }}</div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Genres --}}
                        @if (count($record->genres) > 0)
                            <div>
                                <h3 class="font-semibold text-base-content/90 mb-3 flex items-center">
                                    <x-tabler-music class="w-4 h-4 mr-2" />
                                    Musical Styles
                                </h3>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($record->genres as $genre)
                                        @if ($showEditButton)
                                            <a href="{{ route('filament.member.resources.directory.index', ['tableFilters' => ['genres' => ['values' => [$genre]]]]) }}"
                                                target="_blank"
                                                class="badge badge-outline badge-sm hover:badge-primary">
                                                {{ $genre }}
                                            </a>
                                        @else
                                            <span class="badge badge-outline badge-sm">{{ $genre }}</span>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Influences in full width --}}
                    @if (count($record->influences) > 0)
                        <div class="mt-6">
                            <h3 class="font-semibold text-base-content/90 mb-3 flex items-center">
                                <x-tabler-star class="w-4 h-4 mr-2" />
                                Musical Influences
                            </h3>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($record->influences as $influence)
                                    @if ($showEditButton)
                                        <a href="{{ route('filament.member.resources.directory.index', ['tableFilters' => ['influences' => ['values' => [$influence]]]]) }}"
                                            target="_blank" class="badge badge-accent badge-sm hover:badge-accent/80">
                                            {{ $influence }}
                                        </a>
                                    @else
                                        <span class="badge badge-accent badge-sm">{{ $influence }}</span>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                </section>
            @endif

            {{-- Featured Recordings --}}
            @if ($record->embeds && count($record->embeds) > 0)
                <section>
                    <h2
                        class="text-lg font-bold text-base-content mb-6 uppercase tracking-wide border-b border-base-300 pb-2">
                        Featured Recordings
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
            @elseif($showEditButton && auth()->check() && auth()->user()->can('update', $record))
                <section>
                    <h2
                        class="text-lg font-bold text-base-content mb-6 uppercase tracking-wide border-b border-base-300 pb-2">
                        Featured Recordings
                    </h2>
                    <div class="border-2 border-dashed border-base-300 p-8 text-center bg-base-200">
                        <x-tabler-music class="w-12 h-12 text-base-content/40 mx-auto mb-3" />
                        <h3 class="font-semibold text-base-content mb-2">Share Your Music</h3>
                        <p class="text-base-content/70 text-sm mb-4">
                            Add featured recordings to showcase your musical work
                        </p>
                        <a href="{{ route('filament.member.resources.directory.edit', ['record' => $record->id]) }}"
                            class="btn btn-primary btn-sm uppercase tracking-wide">
                            <x-tabler-plus class="w-4 h-4 mr-2" />
                            Add Content
                        </a>
                    </div>
                </section>
            @endif
        </div>

        {{-- Program Sidebar - Artist Info & Credits --}}
        <div class="bg-base-200 px-6 py-6 space-y-6 border-l border-base-300">

            {{-- Contact Information --}}
            @if ($record->contact && $record->contact->visibility !== 'private')
                <div>
                    <h3
                        class="font-bold text-base-content mb-3 uppercase tracking-wide text-sm border-b border-base-300 pb-1">
                        Artist Contact
                    </h3>
                    <div class="space-y-2 text-sm">
                        @if ($record->contact->email)
                            <a href="mailto:{{ $record->contact->email }}"
                                class="flex items-center text-primary hover:text-primary-focus">
                                <x-tabler-mail class="w-4 h-4 mr-2" />
                                <span>{{ $record->contact->email }}</span>
                            </a>
                        @endif
                        @if ($record->contact->phone)
                            <a href="tel:{{ $record->contact->phone }}"
                                class="flex items-center text-primary hover:text-primary-focus">
                                <x-tabler-phone class="w-4 h-4 mr-2" />
                                <span>{{ $record->contact->phone }}</span>
                            </a>
                        @endif
                        @if ($record->contact->address)
                            <div class="flex items-start text-base-content/70">
                                <x-tabler-map-pin class="w-4 h-4 mr-2 mt-0.5 flex-shrink-0" />
                                <span>{{ $record->contact->address }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Ensemble Credits --}}
            @if ($record->user->bandProfiles && count($record->user->bandProfiles) > 0)
                <div>
                    <h3
                        class="font-bold text-base-content mb-3 uppercase tracking-wide text-sm border-b border-base-300 pb-1">
                        Ensemble Credits
                    </h3>
                    <div class="space-y-2 text-sm">
                        @foreach ($record->user->bandProfiles as $band)
                            @php
                                $isVisible =
                                    $band->visibility === 'public' ||
                                    ($band->visibility === 'members' && auth()->check()) ||
                                    (auth()->check() && auth()->user()->can('view', $band));
                            @endphp
                            <div>
                                @if ($isVisible && $showEditButton)
                                    <a href="{{ route('filament.member.resources.bands.view', ['record' => $band->id]) }}"
                                        class="font-medium text-primary hover:text-primary-focus">
                                        {{ $band->name }}
                                    </a>
                                @elseif($isVisible)
                                    <a href="{{ route('bands.show', $band) }}"
                                        class="font-medium text-primary hover:text-primary-focus">
                                        {{ $band->name }}
                                    </a>
                                @else
                                    <span class="font-medium text-base-content">{{ $band->name }}</span>
                                @endif
                                @if ($band->pivot->position)
                                    <div class="text-xs text-base-content/60 italic">{{ $band->pivot->position }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Artist Links --}}
            @if ($record->links && count($record->links) > 0)
                <div>
                    <h3
                        class="font-bold text-base-content mb-3 uppercase tracking-wide text-sm border-b border-base-300 pb-1">
                        More Information
                    </h3>
                    <div class="space-y-1">
                        @foreach ($record->links as $link)
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
                                    default => 'tabler-world',
                                };
                                $linkColor = match (true) {
                                    str_contains($url, 'spotify') => 'text-success',
                                    str_contains($url, 'youtube') || str_contains($url, 'youtu.be') => 'text-error',
                                    str_contains($url, 'instagram') => 'text-secondary',
                                    str_contains($url, 'facebook') => 'text-info',
                                    str_contains($url, 'soundcloud') => 'text-warning',
                                    str_contains($url, 'bandcamp') => 'text-info',
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
                </div>
            @endif

        </div>
    </div>
</div>
