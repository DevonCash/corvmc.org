@props(['record', 'showEditButton' => false])

{{-- Concert Program Layout - mimics a folded program booklet --}}
<div class="max-w-5xl mx-auto relative">
    <x-profile-navigation :record="$record" type="band" :canEdit="auth()->user()?->can('update', $record)" />
    <x-profile-header :record="$record" type="band" />

    {{-- Program Content - Two Column Layout like program booklet --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-0 bg-base-100 border-x-2 border-base-300">

        {{-- Main Band Details --}}
        <div class="lg:col-span-2 px-8 py-6 space-y-8">

            {{-- Program Notes / Bio --}}
            @if ($record->bio)
                <section>
                    <h2
                        class="text-lg font-bold text-base-content mb-4 uppercase tracking-wide border-b border-base-300 pb-2">
                        About the Band
                    </h2>
                    <div class="prose max-w-none text-base-content/80 leading-relaxed">
                        {!! $record->sanitized_bio !!}
                    </div>
                </section>
            @endif

            {{-- Genres --}}
            @php $genres = $record->tagsWithType('genre')->pluck('name'); @endphp
            @if ($genres->count() > 0)
                <section>
                    <h2 class="text-lg font-bold text-base-content mb-4 uppercase tracking-wide border-b border-base-300 pb-2">
                        Genres
                    </h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($genres as $genre)
                            @if ($showEditButton)
                                <a href="{{ route('filament.member.directory.resources.bands.index', ['tableFilters' => ['genres' => ['values' => [$genre]]]]) }}"
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
            @php $influences = $record->tagsWithType('influence')->pluck('name'); @endphp
            @if ($influences->count() > 0)
                <section>
                    <h2 class="text-lg font-bold text-base-content mb-4 uppercase tracking-wide border-b border-base-300 pb-2">
                        Influences
                    </h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($influences as $influence)
                            @if ($showEditButton)
                                <a href="{{ route('filament.member.directory.resources.bands.index', ['tableFilters' => ['influences' => ['values' => [$influence]]]]) }}"
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

            <x-profile-recordings :record="$record" :editRoute="route('filament.member.directory.resources.bands.edit', ['record' => $record])" type="band" />
        </div>

        {{-- Program Sidebar - Band Info & Credits --}}
        <div class="bg-base-200 px-6 py-6 space-y-6 border-l border-base-300">

            <x-profile-contact :profile="$record" />

            {{-- Band Members --}}
            @if ($record->activeMembers()->count() > 0)
                <div>
                    <h3
                        class="font-bold text-base-content mb-3 uppercase tracking-wide text-sm border-b border-base-300 pb-1">
                        Members
                    </h3>
                    <div class="space-y-2 text-sm">
                        @foreach ($record->activeMembers as $memberRecord)
                            @php
                                $profile = $memberRecord->user->profile;
                                $hasVisibleProfile = $profile?->isVisible(auth()->user());
                            @endphp
                            <div>
                                @if ($hasVisibleProfile && $showEditButton)
                                    <a href="{{ route('filament.member.directory.resources.members.view', ['record' => $profile->id]) }}"
                                        class="font-medium text-primary hover:text-primary-focus">
                                        {{ $memberRecord->user->name }}
                                    </a>
                                @elseif ($hasVisibleProfile)
                                    <a href="{{ route('members.show', $profile) }}"
                                        class="font-medium text-primary hover:text-primary-focus">
                                        {{ $memberRecord->user->name }}
                                    </a>
                                @else
                                    <span class="font-medium text-base-content">{{ $memberRecord->user->name }}</span>
                                @endif
                                @if ($memberRecord->position)
                                    <div class="text-xs text-base-content/60 italic">{{ $memberRecord->position }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Band Links --}}
            @if ($record->links && count($record->links) > 0)
                <div>
                    <h3
                        class="font-bold text-base-content mb-3 uppercase tracking-wide text-sm border-b border-base-300 pb-1">
                        More Information
                    </h3>
                    <x-social-links :links="$record->links" />
                </div>
            @endif

        </div>
    </div>
</div>
