@php
    $user = filament()->auth()->user();
    $stats = $this->getUserStats();
    $activities = $this->getRecentActivity();
    $urls = $this->getStatsUrls();
@endphp

<x-filament-widgets::widget class="fi-user-summary-widget">
    <x-filament::section class="h-80 flex flex-col">
        {{-- User Header --}}
        <div class="flex items-center gap-4 mb-6">
            <x-filament-panels::avatar.user size="lg" :user="$user" loading="lazy" />

            <div class="flex-1 min-w-0">
                <h2 class="text-lg font-semibold text-gray-950 dark:text-white truncate">
                    {{ __('filament-panels::widgets/account-widget.welcome', ['app' => config('app.name')]) }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 truncate">
                    {{ filament()->getUserName($user) }}
                </p>
                @if ($stats['is_sustaining_member'] ?? false)
                    <div class="flex items-center gap-1 mt-1">
                        <x-tabler-star-filled class="w-4 h-4 text-amber-500" />
                        <span class="text-xs text-amber-600 dark:text-amber-400 font-medium">Sustaining Member</span>
                    </div>
                @endif
            </div>

            {{-- Logout Form --}}
            <form action="{{ filament()->getLogoutUrl() }}" method="post" class="fi-account-widget-logout-form">
                @csrf
                <x-filament::button color="gray" :icon="\Filament\Support\Icons\Heroicon::ArrowLeftOnRectangle" :icon-alias="\Filament\View\PanelsIconAlias::WIDGETS_ACCOUNT_LOGOUT_BUTTON" labeled-from="sm" tag="button"
                    type="submit" size="sm">
                    {{ __('filament-panels::widgets/account-widget.actions.logout.label') }}
                </x-filament::button>
            </form>
        </div>

        {{-- Quick Stats Grid --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            {{-- Upcoming Reservations --}}
            <a href="{{ $urls['reservations'] }}"
                class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-3 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                <div class="flex items-center gap-2">
                    <x-tabler-calendar class="w-5 h-5 text-blue-500" />
                    <div>
                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ $stats['upcoming_reservations'] ?? 0 }}
                        </div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">
                            Upcoming
                        </div>
                    </div>
                </div>
            </a>

            {{-- Band Memberships --}}
            <a href="{{ $urls['bands'] }}"
                class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-3 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                <div class="flex items-center gap-2">
                    <x-tabler-music class="w-5 h-5 text-purple-500" />
                    <div>
                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ $stats['band_memberships'] ?? 0 }}
                        </div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">
                            Bands
                        </div>
                    </div>
                </div>
            </a>

            {{-- Productions Managed --}}
            @if (($stats['managed_productions'] ?? 0) > 0)
                <a href="{{ $urls['productions'] }}"
                    class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-3 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                    <div class="flex items-center gap-2">
                        <x-tabler-speakerphone class="w-5 h-5 text-green-500" />
                        <div>
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $stats['managed_productions'] }}
                            </div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                Managing
                            </div>
                        </div>
                    </div>
                </a>
            @endif

            {{-- Free Hours (Sustaining Members) --}}
            @if ($stats['is_sustaining_member'] ?? false)
                <a href="{{ $urls['reservations'] }}"
                    class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-3 hover:bg-amber-100 dark:hover:bg-amber-800/30 transition-colors">
                    <div class="flex items-center gap-2">
                        <x-tabler-clock class="w-5 h-5 text-amber-500" />
                        <div>
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ number_format($stats['remaining_free_hours'] ?? 0, 1) }}h
                            </div>
                            <div class="text-xs text-amber-600 dark:text-amber-400">
                                Free left
                            </div>
                        </div>
                    </div>
                </a>
            @endif
        </div>



        {{-- Profile Completion Prompt --}}
        @if (isset($stats['profile_complete']) && !$stats['profile_complete'])
            <div
                class="mt-4 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                <div class="flex items-start gap-3">
                    <x-tabler-user-edit class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 flex-shrink-0" />
                    <div class="flex-1 min-w-0">
                        <h4 class="text-sm font-medium text-amber-900 dark:text-amber-100">
                            Complete Your Profile
                        </h4>
                        <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">
                            Add a bio, skills, and profile photo to help other members connect with you.
                        </p>
                        <a href="{{ route('filament.member.resources.directory.edit', ['record' => auth()->user()->profile->id]) }}"
                            class="inline-flex items-center gap-1 mt-2 text-xs font-medium text-amber-700 dark:text-amber-300 hover:text-amber-800 dark:hover:text-amber-200">
                            Complete Profile
                            <x-tabler-chevron-right class="w-3 h-3" />
                        </a>
                    </div>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
