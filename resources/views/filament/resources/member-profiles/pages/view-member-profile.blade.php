<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Main Content Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Left Column - Profile Header + Bio & Tags --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Profile Header Section (moved from above) --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex flex-col sm:flex-row gap-6">
                        {{-- Avatar Section --}}
                        <div class="flex-shrink-0 mt-2">
                            <div class="relative">
                                <img
                                    src="{{ $record->avatar_url }}"
                                    alt="{{ $record->user->name }}"
                                    class="fi-avatar fi-avatar-lg rounded-full object-cover mx-auto"
                                    style="width: 120px; height: 120px;"
                                >

                                {{-- Private Profile Indicator --}}
                                @if($record->visibility === 'private')
                                    <div class="absolute -top-2 -right-2">
                                        <div class="bg-red-600 text-white px-3 py-1 rounded-full text-xs font-semibold shadow-lg flex items-center">
                                            <x-heroicon-s-lock-closed class="w-3 h-3 mr-1" />
                                            Private
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Member Since --}}
                            <div class="text-xs text-gray-500 text-center mt-3 leading-tight">
                                <div>Member since</div>
                                <div>{{ $record->created_at->format('F Y') }}</div>
                            </div>
                        </div>

                        {{-- Profile Info --}}
                        <div class="flex-1">
                            <div class="space-y-4">
                                <div>
                                    {{-- Location --}}
                                    @if($record->hometown)
                                        <div class="flex items-center text-gray-500 text-sm">
                                            <x-heroicon-s-map-pin class="w-3 h-3 mr-1" />
                                            <span>{{ $record->hometown }}</span>
                                        </div>
                                    @endif

                                    {{-- Name and Pronouns --}}
                                    <div>
                                        <h1 class="text-3xl font-bold text-gray-900">
                                            {{ $record->user->name }}
                                            @if($record->user->pronouns)
                                                <span class="text-lg text-gray-600 font-normal relative -top-[0.18rem] whitespace-nowrap">({{ $record->user->pronouns }})</span>
                                            @endif
                                        </h1>
                                    </div>
                                </div>

                                {{-- Directory Flags --}}
                                @php $activeFlags = $record->getActiveFlagsWithLabels(); @endphp
                                @if(count($activeFlags) > 0)
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($activeFlags as $flag => $label)
                                            @php
                                                $flagIcon = match($flag) {
                                                    'open_to_collaboration' => 'heroicon-s-hand-raised',
                                                    'available_for_hire' => 'heroicon-s-currency-dollar',
                                                    'looking_for_band' => 'heroicon-s-musical-note',
                                                    'music_teacher' => 'heroicon-s-academic-cap',
                                                    default => 'heroicon-s-star'
                                                };
                                                $flagColor = match($flag) {
                                                    'open_to_collaboration' => 'bg-blue-100 text-blue-800',
                                                    'available_for_hire' => 'bg-green-100 text-green-800',
                                                    'looking_for_band' => 'bg-purple-100 text-purple-800',
                                                    'music_teacher' => 'bg-orange-100 text-orange-800',
                                                    default => 'bg-gray-100 text-gray-800'
                                                };
                                            @endphp
                                            <a href="{{ route('filament.member.resources.directory.index', ['tableFilters' => ['directory_flags' => ['value' => $flag]]]) }}"
                                               target="_blank"
                                               class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $flagColor }} hover:opacity-80 transition-opacity">
                                                @svg($flagIcon, 'w-4 h-4 mr-1')
                                                {{ $label }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Contact Information --}}
                                @if($record->contact && $record->contact->visibility !== 'private')
                                    <div class="space-y-2">
                                        @if($record->contact->email)
                                            <div class="flex items-center">
                                                <x-heroicon-s-envelope class="w-4 h-4 text-gray-400 mr-2 flex-shrink-0" />
                                                <a href="mailto:{{ $record->contact->email }}"
                                                   class="text-blue-600 hover:text-blue-800 text-sm break-all">
                                                    {{ $record->contact->email }}
                                                </a>
                                            </div>
                                        @endif
                                        @if($record->contact->phone)
                                            <div class="flex items-center">
                                                <x-heroicon-s-phone class="w-4 h-4 text-gray-400 mr-2 flex-shrink-0" />
                                                <a href="tel:{{ $record->contact->phone }}"
                                                   class="text-blue-600 hover:text-blue-800 text-sm">
                                                    {{ $record->contact->phone }}
                                                </a>
                                            </div>
                                        @endif
                                        @if($record->contact->address)
                                            <div class="flex items-start">
                                                <x-heroicon-s-map-pin class="w-4 h-4 text-gray-400 mr-2 mt-0.5 flex-shrink-0" />
                                                <span class="text-gray-600 text-sm">{{ $record->contact->address }}</span>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                {{-- About Section --}}
                @if($record->bio || count($record->skills) > 0 || count($record->genres) > 0 || count($record->influences) > 0)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                            About
                        </h2>
                        
                        @if($record->bio)
                            <div class="prose prose-gray max-w-none mb-6">
                                {!! $record->bio !!}
                            </div>
                        @endif

                        @if(count($record->skills) > 0)
                            <div class="mb-6">
                                <h3 class="text-sm font-medium text-gray-900 mb-3 flex items-center">
                                    <x-tabler-tools class="size-4 mr-1" />
                                    Skills & Instruments
                                </h3>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($record->skills as $skill)
                                        <a href="{{ route('filament.member.resources.directory.index', ['tableFilters' => ['skills' => ['values' => [$skill]]]]) }}"
                                           target="_blank"
                                           class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 hover:bg-blue-200 transition-colors">
                                            {{ $skill }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(count($record->genres) > 0)
                            <div class="mb-6">
                                <h3 class="text-sm font-medium text-gray-900 mb-3 flex items-center">
                                    <x-tabler-music class="size-4 mr-1" />
                                    Musical Genres
                                </h3>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($record->genres as $genre)
                                        <a href="{{ route('filament.member.resources.directory.index', ['tableFilters' => ['genres' => ['values' => [$genre]]]]) }}"
                                           target="_blank"
                                           class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800 hover:bg-purple-200 transition-colors">
                                            {{ $genre }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(count($record->influences) > 0)
                            <div>
                                <h3 class="text-sm font-medium text-gray-900 mb-3 flex items-center">
                                    <x-tabler-star class="w-3 h-3 mr-1" />
                                    Musical Influences
                                </h3>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($record->influences as $influence)
                                        <a href="{{ route('filament.member.resources.directory.index', ['tableFilters' => ['influences' => ['values' => [$influence]]]]) }}"
                                           target="_blank"
                                           class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800 hover:bg-gray-200 transition-colors">
                                            {{ $influence }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Right Column - Links & Info --}}
            <div class="space-y-6">

                {{-- Embeds & Widgets --}}
                @if($record->embeds && count($record->embeds) > 0)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                            <x-heroicon-s-play class="w-4 h-4 mr-2" />
                            Featured Content
                        </h2>
                        <div class="space-y-4">
                            @foreach($record->embeds as $embed)
                                <div class="relative">
                                    @if($embed['type'] === 'iframe')
                                        <div class="aspect-video rounded-lg overflow-hidden">
                                            <iframe
                                                src="{{ $embed['url'] }}"
                                                title="{{ $embed['title'] ?? 'Embedded content' }}"
                                                class="w-full h-full border-0"
                                                loading="lazy"
                                                allowfullscreen>
                                            </iframe>
                                        </div>
                                    @elseif($embed['type'] === 'bandcamp')
                                        <div class="bandcamp-embed">
                                            {!! $embed['html'] !!}
                                        </div>
                                    @elseif($embed['type'] === 'soundcloud')
                                        <div class="soundcloud-embed">
                                            {!! $embed['html'] !!}
                                        </div>
                                    @else
                                        <div class="bg-gray-100 rounded-lg p-4 text-center">
                                            <a href="{{ $embed['url'] }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="text-blue-600 hover:text-blue-800 font-medium">
                                                {{ $embed['title'] ?? 'View Content' }}
                                            </a>
                                        </div>
                                    @endif
                                    @if($embed['title'] && $embed['type'] !== 'link')
                                        <p class="text-sm text-gray-600 mt-2">{{ $embed['title'] }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    {{-- Placeholder for when no embeds exist --}}
                    <div class="bg-gray-50 rounded-xl border-2 border-dashed border-gray-200 p-8 text-center">
                        <x-heroicon-s-musical-note class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Share Your Music</h3>
                        <p class="text-gray-600 text-sm mb-4">
                            Add Bandcamp, SoundCloud, YouTube, or other embeds to showcase your work
                        </p>
                        @can('update', $record)
                            <a href="{{ route('filament.member.resources.directory.edit', ['record' => $record->id]) }}"
                               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors">
                                <x-heroicon-s-plus class="w-4 h-4 mr-2" />
                                Add Content
                            </a>
                        @endif
                    </div>
                @endif
                {{-- Band Affiliations --}}
                @if($record->user->bandProfiles && count($record->user->bandProfiles) > 0)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                            <x-tabler-users-group class="w-4 h-4 mr-2" />
                            Bands & Groups
                        </h2>
                        <div class="space-y-3">
                            @foreach($record->user->bandProfiles as $band)
                                @php
                                    // Check if band profile is visible to current user
                                    $isVisible = $band->visibility === 'public' || 
                                               ($band->visibility === 'members' && auth()->check()) ||
                                               (auth()->check() && auth()->user()->can('view', $band));
                                @endphp
                                <div class="flex items-center justify-between">
                                    <div class="min-w-0 flex-1">
                                        @if($isVisible)
                                            <a href="{{ route('filament.member.resources.bands.view', ['record' => $band->id]) }}"
                                               class="font-medium text-blue-600 hover:text-blue-800 truncate block">
                                                {{ $band->name }}
                                            </a>
                                        @else
                                            <span class="font-medium text-gray-900 truncate block">{{ $band->name }}</span>
                                        @endif
                                        @if($band->pivot->position)
                                            <p class="text-xs text-gray-500 truncate">{{ $band->pivot->position }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    {{-- Placeholder for when no bands exist --}}
                    <div class="bg-gray-50 rounded-xl border-2 border-dashed border-gray-200 p-6 text-center">
                        <x-heroicon-s-user-group class="w-8 h-8 text-gray-400 mx-auto mb-3" />
                        <h3 class="text-sm font-medium text-gray-900 mb-1">No Band Affiliations</h3>
                        <p class="text-gray-600 text-xs">
                            Band memberships will appear here
                        </p>
                    </div>
                @endif
                {{-- Links Section --}}
                @if($record->links && count($record->links) > 0)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                            <x-heroicon-s-link class="w-4 h-4 mr-2" />
                            Links
                        </h2>
                        <div class="space-y-3">
                            @foreach($record->links as $link)
                                @php
                                    $url = strtolower($link['url']);
                                    $linkIcon = match(true) {
                                        str_contains($url, 'spotify') => 'heroicon-s-musical-note',
                                        str_contains($url, 'youtube') || str_contains($url, 'youtu.be') => 'heroicon-s-play',
                                        str_contains($url, 'instagram') => 'heroicon-s-camera',
                                        str_contains($url, 'facebook') => 'heroicon-s-user-group',
                                        str_contains($url, 'twitter') || str_contains($url, 'x.com') => 'heroicon-s-chat-bubble-left',
                                        str_contains($url, 'tiktok') => 'heroicon-s-device-phone-mobile',
                                        str_contains($url, 'soundcloud') => 'heroicon-s-speaker-wave',
                                        str_contains($url, 'bandcamp') => 'heroicon-s-musical-note',
                                        str_contains($url, 'github') => 'heroicon-s-code-bracket',
                                        str_contains($url, 'linkedin') => 'heroicon-s-briefcase',
                                        str_contains($url, 'mailto:') => 'heroicon-s-envelope',
                                        str_contains($url, 'tel:') => 'heroicon-s-phone',
                                        default => 'heroicon-s-globe-alt'
                                    };
                                    $linkColor = match(true) {
                                        str_contains($url, 'spotify') => 'text-green-600',
                                        str_contains($url, 'youtube') || str_contains($url, 'youtu.be') => 'text-red-600',
                                        str_contains($url, 'instagram') => 'text-pink-600',
                                        str_contains($url, 'facebook') => 'text-blue-600',
                                        str_contains($url, 'twitter') || str_contains($url, 'x.com') => 'text-sky-600',
                                        str_contains($url, 'tiktok') => 'text-black',
                                        str_contains($url, 'soundcloud') => 'text-orange-600',
                                        str_contains($url, 'bandcamp') => 'text-blue-500',
                                        str_contains($url, 'github') => 'text-gray-800',
                                        str_contains($url, 'linkedin') => 'text-blue-700',
                                        default => 'text-gray-500'
                                    };
                                @endphp
                                <a href="{{ $link['url'] }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                    <div class="flex items-center">
                                        @svg($linkIcon, 'w-4 h-4 mr-3 ' . $linkColor)
                                        <span class="font-medium text-gray-900">{{ $link['name'] }}</span>
                                    </div>
                                    <x-heroicon-s-arrow-top-right-on-square class="w-3 h-3 text-gray-500" />
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
</x-filament-panels::page>
