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
                        {!! $record->sanitized_bio !!}
                    </div>
                </section>
            @endif

            {{-- Skills --}}
            @if (count($record->skills) > 0)
                <section>
                    <h2 class="text-lg font-bold text-base-content mb-4 uppercase tracking-wide border-b border-base-300 pb-2">
                        Instruments / Skills
                    </h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($record->skills as $skill)
                            @if ($showEditButton)
                                <a href="{{ route('filament.member.directory.resources.members.index', ['tableFilters' => ['skills' => ['values' => [$skill]]]]) }}"
                                    target="_blank" class="badge badge-outline badge-primary badge-sm hover:badge-primary">
                                    {{ $skill }}
                                </a>
                            @else
                                <span class="badge badge-outline badge-primary badge-sm">{{ $skill }}</span>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Genres --}}
            @if (count($record->genres) > 0)
                <section>
                    <h2 class="text-lg font-bold text-base-content mb-4 uppercase tracking-wide border-b border-base-300 pb-2">
                        Genres
                    </h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($record->genres as $genre)
                            @if ($showEditButton)
                                <a href="{{ route('filament.member.directory.resources.members.index', ['tableFilters' => ['genres' => ['values' => [$genre]]]]) }}"
                                    target="_blank" class="badge badge-outline badge-secondary badge-sm hover:badge-secondary">
                                    {{ $genre }}
                                </a>
                            @else
                                <span class="badge badge-outline badge-secondary badge-sm">{{ $genre }}</span>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Influences --}}
            @if (count($record->influences) > 0)
                <section>
                    <h2 class="text-lg font-bold text-base-content mb-4 uppercase tracking-wide border-b border-base-300 pb-2">
                        Influences
                    </h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($record->influences as $influence)
                            @if ($showEditButton)
                                <a href="{{ route('filament.member.directory.resources.members.index', ['tableFilters' => ['influences' => ['values' => [$influence]]]]) }}"
                                    target="_blank" class="badge badge-outline badge-accent badge-sm hover:badge-accent">
                                    {{ $influence }}
                                </a>
                            @else
                                <span class="badge badge-outline badge-accent badge-sm">{{ $influence }}</span>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endif

            <x-profile-recordings
                :record="$record"
                :editRoute="route('filament.member.directory.resources.members.edit', ['record' => $record->id])"
                type="member" />
        </div>

        {{-- Program Sidebar - Artist Info & Credits --}}
        <div class="bg-base-200 px-6 py-6 space-y-6 border-l border-base-300">

            <x-profile-contact :profile="$record" />

            {{-- Ensemble Credits --}}
            @if ($record->user->bands && count($record->user->bands) > 0)
                <div>
                    <h3
                        class="font-bold text-base-content mb-3 uppercase tracking-wide text-sm border-b border-base-300 pb-1">
                        Bands
                    </h3>
                    <div class="space-y-2 text-sm">
                        @foreach ($record->user->bands as $band)
                            @php
                                $isVisible =
                                    $band->visibility === 'public' ||
                                    ($band->visibility === 'members' && auth()->check()) ||
                                    (auth()->check() && auth()->user()->can('view', $band));
                            @endphp
                            <div>
                                @if ($isVisible && $showEditButton)
                                    <a href="{{ route('filament.member.directory.resources.bands.view', ['record' => $band]) }}"
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
