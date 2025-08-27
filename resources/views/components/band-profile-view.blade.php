@props(['record', 'showEditButton' => false])

{{-- Concert Program Layout - mimics a folded program booklet --}}
<div class="max-w-5xl mx-auto relative">


    @if ($showEditButton && auth()->check() && auth()->user()->can('update', $record))
        <div class="absolute top-4 right-4">
            <a href="{{ route('filament.member.resources.bands.edit', ['record' => $record->id]) }}"
                class="btn btn-sm btn-primary uppercase tracking-wide">
                <x-tabler-edit class="w-4 h-4 mr-2" />
                Edit Band
            </a>
        </div>
    @endif

    {{-- Program Cover/Header --}}
    <div class="bg-base-200 border-b-2 border-base-300 px-8 py-2">
        <div class="text-center mb-6">
            <div class="inline-block">
                <div class="text-xs uppercase tracking-widest text-base-content/60 mb-1">
                    Member since {{ $record->created_at->format('Y') }}
                </div>
                <div class="w-16 h-0.5 bg-primary mx-auto"></div>
            </div>
        </div>



        <div class="flex flex-col lg:flex-row items-center gap-6 mb-6">
            {{-- Band Photo --}}
            <div class="relative">
                <img src="{{ $record->avatar_url }}" alt="{{ $record->name }}"
                    class="w-32 h-32 object-cover border-4 border-base-100"
                    style="clip-path: polygon(0 0, 100% 0, 95% 100%, 5% 100%);">
                @if ($record->visibility === 'private')
                    <div class="absolute -top-2 -right-2">
                        <div class="bg-error text-error-content px-2 py-1 text-xs font-bold flex items-center">
                            <x-tabler-lock class="w-3 h-3 mr-1" />
                            PRIVATE
                        </div>
                    </div>
                @elseif ($record->visibility === 'members')
                    <div class="absolute -top-2 -right-2">
                        <div class="bg-warning text-warning-content px-2 py-1 text-xs font-bold flex items-center">
                            <x-tabler-users class="w-3 h-3 mr-1" />
                            MEMBERS
                        </div>
                    </div>
                @endif
            </div>

            {{-- Band Details --}}
            <div class="text-center lg:text-left flex-1">
                <h1 class="text-4xl font-bold text-base-content mb-2 tracking-tight">
                    {{ $record->name }}
                </h1>

                <div class="flex items-center justify-center lg:justify-start text-base-content/70 mb-2">
                    <x-tabler-users class="w-4 h-4 mr-1" />
                    <span>{{ $record->members()->count() }}
                        {{ $record->members()->count() === 1 ? 'member' : 'members' }}</span>
                </div>

                @if ($record->hometown)
                    <div class="flex items-center justify-center lg:justify-start text-base-content/70 mb-4">
                        <x-tabler-map-pin class="w-4 h-4 mr-1" />
                        <span>{{ $record->hometown }}</span>
                    </div>
                @endif

                {{-- Genres --}}
                @php $genres = $record->tagsWithType('genre')->pluck('name'); @endphp
                @if ($genres->count() > 0)
                    <div class="flex flex-wrap gap-2 justify-center lg:justify-start">
                        @foreach ($genres as $genre)
                            <span class="badge badge-secondary badge-sm font-bold uppercase tracking-wide">
                                {{ $genre }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>


        {{-- Visibility Notice for Private/Member-Only Profiles --}}
        @if (!$showEditButton && auth()->check())
            @if ($record->owner_id === auth()->id() || $record->members->contains(auth()->user()))
                <div class="mb-2 p-4 bg-info text-info-content rounded-lg border-l-4 border-info-content">
                    <div class="flex items-center">
                        <x-tabler-info-circle class="w-5 h-5 mr-3 flex-shrink-0" />
                        <div>
                            <h3 class="font-semibold">
                                @if ($record->owner_id === auth()->id())
                                    This is your band
                                @else
                                    You're a member of this band
                                @endif
                            </h3>
                            <p class="text-sm opacity-90">
                                You're viewing this band profile as it appears to
                                @if ($record->visibility === 'private')
                                    <strong>band members only</strong> (private)
                                @elseif($record->visibility === 'members')
                                    <strong>logged-in members</strong> (members-only)
                                @else
                                    <strong>everyone</strong> (public)
                                @endif
                            </p>
                        </div>
                        @can('update', $record)
                            <div>
                                <a href="{{ route('filament.member.resources.bands.edit', ['record' => $record->id]) }}"
                                    class="btn btn-sm btn-outline btn-info ml-6">
                                    <x-tabler-edit class="w-4 h-4 mr-2" />
                                    Edit
                                </a>
                            </div>
                        @endcan
                    </div>
                </div>
            @elseif ($record->visibility === 'private')
                <div class="mb-6 p-4 bg-warning text-warning-content rounded-lg border-l-4 border-warning-content">
                    <div class="flex items-center">
                        <x-tabler-lock class="w-5 h-5 mr-3 flex-shrink-0" />
                        <div>
                            <h3 class="font-semibold">Private Band Profile</h3>
                            <p class="text-sm opacity-90">This band profile is private and only visible to you because
                                you have special access.</p>
                        </div>
                    </div>
                </div>
            @elseif ($record->visibility === 'members')
                <div
                    class="mb-6 p-4 bg-secondary text-secondary-content rounded-lg border-l-4 border-secondary-content">
                    <div class="flex items-center">
                        <x-tabler-users class="w-5 h-5 mr-3 flex-shrink-0" />
                        <div>
                            <h3 class="font-semibold">Member Band Profile</h3>
                            <p class="text-sm opacity-90">This band profile is only visible to logged-in community
                                members.</p>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>

    {{-- Program Content - Two Column Layout like program booklet --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-0 bg-base-100">

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
                        {!! $record->bio !!}
                    </div>
                </section>
            @endif

            {{-- Musical Influences --}}
            @php $influences = $record->tagsWithType('influence')->pluck('name'); @endphp
            @if ($influences->count() > 0)
                <section>
                    <h2
                        class="text-lg font-bold text-base-content mb-4 uppercase tracking-wide border-b border-base-300 pb-2">
                        Musical Influences
                    </h2>
                    <div class="flex flex-wrap gap-1">
                        @foreach ($influences as $influence)
                            @if ($showEditButton)
                                <a href="{{ route('filament.member.resources.bands.index', ['tableFilters' => ['influences' => ['values' => [$influence]]]]) }}"
                                    target="_blank" class="badge badge-accent badge-sm hover:badge-accent/80">
                                    {{ $influence }}
                                </a>
                            @else
                                <span class="badge badge-accent badge-sm">{{ $influence }}</span>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Band Members --}}
            @if ($record->activeMembers()->count() > 0)
                <section>
                    <h2
                        class="text-lg font-bold text-base-content mb-6 uppercase tracking-wide border-b border-base-300 pb-2">
                        Band Members
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach ($record->activeMembers as $member)
                            @php
                                $displayName = $member->pivot->name ?: ($member ? $member->name : 'Unknown Member');
                                // Check if member has a CMC account and profile
                                $hasProfile = false;
                                if ($member && $member->profile) {
                                    try {
                                        $hasProfile = $member->profile->isVisible(auth()->user());
                                    } catch (Exception $e) {
                                        // If isVisible method doesn't exist or fails, check basic visibility
        $hasProfile =
            $member->profile->visibility === 'public' ||
            ($member->profile->visibility === 'members' && auth()->check());
                                    }
                                }
                            @endphp
                            <div class="flex items-center justify-between p-3 bg-base-200 rounded-lg">
                                <div class="min-w-0 flex-1">
                                    @if ($hasProfile)
                                        <a href="{{ $showEditButton ? route('filament.member.resources.directory.view', ['record' => $member->profile->id]) : route('members.show', $member->profile) }}"
                                            class="font-medium text-primary hover:text-primary-focus truncate block">
                                            {{ $displayName }}
                                        </a>
                                    @elseif($member)
                                        <span class="font-medium text-base-content truncate block"
                                            title="CMC Member (profile not visible)">{{ $displayName }}</span>
                                    @else
                                        <span
                                            class="font-medium text-base-content truncate block">{{ $displayName }}</span>
                                    @endif
                                    @if ($member && $member->pivot && $member->pivot->position)
                                        <p class="text-xs text-base-content/60 truncate">{{ $member->pivot->position }}
                                        </p>
                                    @endif
                                </div>
                                @if (!$member)
                                    <div class="flex-shrink-0 ml-2">
                                        <span class="badge badge-outline badge-xs">Guest</span>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
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
                            Add Bandcamp, SoundCloud, YouTube, or other embeds to showcase your work
                        </p>
                        <a href="{{ route('filament.member.resources.bands.edit', ['record' => $record->id]) }}"
                            class="btn btn-primary btn-sm uppercase tracking-wide">
                            <x-tabler-plus class="w-4 h-4 mr-2" />
                            Add Content
                        </a>
                    </div>
                </section>
            @endif
        </div>

        {{-- Program Sidebar - Band Info & Credits --}}
        <div class="bg-base-200 px-6 py-6 space-y-6 border-l border-base-300">

            {{-- Contact Information --}}
            @if ($record->contact && $record->contact['visibility'] !== 'private')
                <div>
                    <h3
                        class="font-bold text-base-content mb-3 uppercase tracking-wide text-sm border-b border-base-300 pb-1">
                        Band Contact
                    </h3>
                    <div class="space-y-2 text-sm">
                        @if ($record->contact['email'])
                            <a href="mailto:{{ $record->contact['email'] }}"
                                class="flex items-center text-primary hover:text-primary-focus">
                                <x-tabler-mail class="w-4 h-4 mr-2" />
                                <span>{{ $record->contact['email'] }}</span>
                            </a>
                        @endif
                        @if ($record->contact['phone'])
                            <a href="tel:{{ $record->contact['phone'] }}"
                                class="flex items-center text-primary hover:text-primary-focus">
                                <x-tabler-phone class="w-4 h-4 mr-2" />
                                <span>{{ $record->contact['phone'] }}</span>
                            </a>
                        @endif
                        @if (isset($record->contact['address']) && $record->contact['address'])
                            <div class="flex items-start text-base-content/70">
                                <x-tabler-map-pin class="w-4 h-4 mr-2 mt-0.5 flex-shrink-0" />
                                <span>{{ $record->contact['address'] }}</span>
                            </div>
                        @endif
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
