@props(['record', 'showEditButton' => false])

{{-- Concert Program Layout - mimics a folded program booklet --}}
<div class="max-w-5xl mx-auto relative">
    <x-profile-navigation :record="$record" type="member" :canEdit="auth()->user()?->can('update', $record)" />
    <x-profile-header :record="$record" type="member" />

    {{-- Program Content - Two Column Layout like program booklet --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-0 bg-base-100 border-x-2 border-base-300">

        {{-- Main Performance Details --}}
        <div class="lg:col-span-2 px-8 py-6 space-y-8">

            {{-- Program Notes / Bio --}}
            @if ($record->bio)
                <section>
                    <h2
                        class="text-lg font-bold text-base-content mb-4 uppercase tracking-wide border-b border-base-300 pb-2">
                        Artist Bio
                    </h2>
                    <div class="prose max-w-none text-base-content/80 leading-relaxed">
                        {!! $record->bio !!}
                    </div>
                </section>
            @endif

            {{-- Musical Repertoire --}}
            @if (count($record->skills) > 0 || count($record->genres) > 0 || count($record->influences) > 0)
                <section>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        {{-- Instruments & Skills --}}
                        @if (count($record->skills) > 0)
                            <div>
                                <h3 class="font-semibold text-base-content/90 mb-3 flex items-center">
                                    <x-tabler-tools class="w-4 h-4 mr-2" />
                                    Instruments / Skills
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
                                    Genres
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
                                Influences
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

            <x-profile-recordings
                :embeds="$record->embeds"
                :canEdit="$showEditButton && auth()->check() && auth()->user()->can('update', $record)"
                :editRoute="route('filament.member.resources.directory.edit', ['record' => $record->id])"
                type="member" />
        </div>

        {{-- Program Sidebar - Artist Info & Credits --}}
        <div class="bg-base-200 px-6 py-6 space-y-6 border-l border-base-300">

            <x-profile-contact :profile="$record" />

            {{-- Ensemble Credits --}}
            @if ($record->user->bandProfiles && count($record->user->bandProfiles) > 0)
                <div>
                    <h3
                        class="font-bold text-base-content mb-3 uppercase tracking-wide text-sm border-b border-base-300 pb-1">
                        Bands
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
                                    <a href="{{ route('filament.member.resources.bands.view', ['record' => $band]) }}"
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
                                    <div class="text-xs text-base-content/60 italic">{{ $band->pivot->position }}
                                    </div>
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
                        Links
                    </h3>
                    <x-social-links :links="$record->links" />
                </div>
            @endif
        </div>
    </div>
</div>
