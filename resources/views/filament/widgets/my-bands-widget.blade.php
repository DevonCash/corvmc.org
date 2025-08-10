@php
    $bands = $this->getMyBands();
@endphp

<div class="fi-wi-stats-overview grid gap-6">
    <div class="fi-wi-stat rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex items-center gap-3 mb-4">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-warning-50 dark:bg-warning-900/10">
                <x-tabler-users class="h-5 w-5 text-warning-600 dark:text-warning-400" />
            </div>
            <div class="flex-1">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                    My Bands
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $bands['total'] }} active {{ Str::plural('band', $bands['total']) }}
                    @if($bands['invitations']->count() > 0)
                        • {{ $bands['invitations']->count() }} {{ Str::plural('invitation', $bands['invitations']->count()) }}
                    @endif
                </p>
            </div>
            @if(auth()->user()?->can('create bands'))
                <a href="{{ route('filament.member.resources.bands.create') }}"
                   class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-primary-600 text-white hover:bg-primary-500 dark:bg-primary-500 dark:hover:bg-primary-400"
                   title="Create Band">
                    <x-tabler-plus class="h-4 w-4" />
                </a>
            @endif
        </div>

        {{-- Pending Invitations --}}
        @if($bands['invitations']->count() > 0)
            <div class="mb-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                <div class="flex items-center gap-2 mb-2">
                    <x-tabler-mail class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                    <h4 class="text-sm font-medium text-amber-800 dark:text-amber-200">
                        Band Invitations
                    </h4>
                </div>

                <div class="space-y-2">
                    @foreach($bands['invitations'] as $invitation)
                        <div class="flex items-center justify-between bg-white dark:bg-gray-800 p-2 rounded border">
                            <div class="flex items-center gap-2">
                                @if($invitation['avatar_thumb_url'])
                                    <img src="{{ $invitation['avatar_thumb_url'] }}"
                                         alt="{{ $invitation['name'] }}"
                                         class="w-6 h-6 rounded object-cover">
                                @else
                                    <div class="w-6 h-6 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center">
                                        <x-tabler-music class="h-3 w-3 text-gray-500" />
                                    </div>
                                @endif
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $invitation['name'] }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        from {{ $invitation['owner_name'] }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-1">
                                <button class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded hover:bg-green-200 dark:bg-green-900/20 dark:text-green-200">
                                    Accept
                                </button>
                                <button class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded hover:bg-red-200 dark:bg-red-900/20 dark:text-red-200">
                                    Decline
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- My Bands List --}}
        <div class="space-y-3">
            @forelse(collect($bands['owned'])->concat($bands['member']) as $band)
                <div class="flex gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700">
                    {{-- Band Avatar --}}
                    @if($band['avatar_thumb_url'])
                        <div class="flex-shrink-0">
                            <img src="{{ $band['avatar_thumb_url'] }}"
                                 alt="{{ $band['name'] }}"
                                 class="w-12 h-12 rounded-lg object-cover">
                        </div>
                    @else
                        <div class="flex-shrink-0 w-12 h-12 bg-warning-100 dark:bg-warning-900/20 rounded-lg flex items-center justify-center">
                            <x-tabler-users class="h-6 w-6 text-warning-600 dark:text-warning-400" />
                        </div>
                    @endif

                    {{-- Band Info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <h4 class="text-sm font-semibold text-gray-950 dark:text-white truncate">
                                        {{ $band['name'] }}
                                    </h4>

                                    {{-- Role Badge --}}
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium {{
                                        $band['is_owner'] ? 'bg-primary-100 text-primary-800 dark:bg-primary-900/20 dark:text-primary-200' :
                                        ($band['user_role'] === 'admin' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/20 dark:text-amber-200' :
                                        'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200')
                                    }}">
                                        {{ ucfirst($band['user_role']) }}
                                    </span>

                                    {{-- Visibility Badge --}}
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs {{
                                        $band['visibility'] === 'public' ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-200' :
                                        ($band['visibility'] === 'members' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-200' :
                                        'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200')
                                    }}">
                                        <x-tabler-eye class="h-3 w-3 mr-0.5" />
                                        {{ ucfirst($band['visibility']) }}
                                    </span>
                                </div>

                                {{-- Member Count --}}
                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                    <x-tabler-users class="inline h-3 w-3 mr-1" />
                                    {{ $band['member_count'] }} active {{ Str::plural('member', $band['member_count']) }}
                                    @if($band['pending_invitations'] > 0)
                                        • {{ $band['pending_invitations'] }} pending
                                    @endif
                                </p>

                                {{-- Genres --}}
                                @if(!empty($band['genres']))
                                    <div class="flex flex-wrap gap-1 mt-1">
                                        @foreach(array_slice($band['genres'], 0, 3) as $genre)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-secondary-100 text-secondary-800 dark:bg-secondary-900/20 dark:text-secondary-200">
                                                {{ $genre }}
                                            </span>
                                        @endforeach
                                        @if(count($band['genres']) > 3)
                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                +{{ count($band['genres']) - 3 }}
                                            </span>
                                        @endif
                                    </div>
                                @endif

                                {{-- Recent Productions --}}
                                @if($band['recent_productions']->count() > 0)
                                    <div class="mt-2">
                                        <p class="text-xs text-gray-500 dark:text-gray-500 mb-1">Recent shows:</p>
                                        @foreach($band['recent_productions'] as $production)
                                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                                • {{ $production['title'] }} at {{ $production['venue_name'] }}
                                                ({{ $production['start_time']->format('M j') }})
                                            </p>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            {{-- Quick Actions --}}
                            <div class="flex items-center gap-1 ml-2">
                                <a href="{{ $band['view_url'] }}"
                                   title="View Band"
                                   class="inline-flex items-center justify-center w-6 h-6 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                                    <x-tabler-eye class="h-3 w-3" />
                                </a>

                                @if($band['edit_url'])
                                    <a href="{{ $band['edit_url'] }}"
                                       title="Edit Band"
                                       class="inline-flex items-center justify-center w-6 h-6 text-amber-500 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-200">
                                        <x-tabler-edit class="h-3 w-3" />
                                    </a>
                                @endif

                                @if($band['can_manage'] && $band['pending_invitations'] > 0)
                                    <a href="{{ $band['edit_url'] }}#members"
                                       title="Manage Invitations"
                                       class="inline-flex items-center justify-center w-6 h-6 text-primary-500 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-200">
                                        <x-tabler-mail class="h-3 w-3" />
                                    </a>
                                @endif

                                @if($band['primary_link'])
                                    <a href="{{ $band['primary_link']['url'] }}"
                                       target="{{ $band['primary_link']['external'] ? '_blank' : '_self' }}"
                                       title="{{ $band['primary_link']['text'] }}"
                                       class="inline-flex items-center justify-center w-6 h-6 text-green-500 hover:text-green-700 dark:text-green-400 dark:hover:text-green-200">
                                        <x-dynamic-component :component="str_replace('tabler:', 'tabler-', $band['primary_link']['icon'])" class="h-3 w-3" />
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-8">
                    <x-unicon name="tabler:users-off" class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        You're not in any bands yet
                    </p>
                    @if(auth()->user()?->can('create bands'))
                        <a href="{{ route('filament.member.resources.bands.create') }}"
                           class="mt-2 inline-flex items-center text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">
                            <x-tabler-plus class="h-4 w-4 mr-1" />
                            Create Your First Band
                        </a>
                    @endif
                </div>
            @endforelse
        </div>

        @if($bands['total'] > 0)
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="text-center">
                    <a href="{{ route('filament.member.resources.bands.index') }}"
                       class="text-sm font-medium text-warning-600 hover:text-warning-500 dark:text-warning-400 dark:hover:text-warning-300">
                        Manage all bands
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
