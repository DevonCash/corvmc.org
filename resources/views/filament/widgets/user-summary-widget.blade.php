@php
    $user = filament()->auth()->user();
    $stats = $this->getUserStats();
    $activities = $this->getRecentActivity();
    $urls = $this->getStatsUrls();
@endphp

<x-filament-widgets::widget class="fi-user-summary-widget">
    <x-filament::section class=" flex flex-col">
        {{-- User Header --}}
        <div class="flex items-center gap-4 ">
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
    </x-filament::section>
</x-filament-widgets::widget>
