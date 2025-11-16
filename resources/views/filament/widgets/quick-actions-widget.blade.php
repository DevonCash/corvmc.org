@php
    $actions = $this->getQuickActions();
    $user = filament()->auth()->user();
    $stats = $this->getUserStats();
@endphp

<x-filament-widgets::widget class="fi-quick-actions-widget">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        {{-- User Identity Card --}}
        <div class="fi-section p-4 flex flex-col gap-3 col-span-2 md:row-span-2 md:col-start-1 md:row-start-1 justify-between">
            <div class="flex items-center gap-3">
                <x-filament-panels::avatar.user size="lg" :user="$user" loading="lazy" />
                <div class="flex-1 min-w-0">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white truncate flex items-center gap-2">
                        {{ filament()->getUserName($user) }}
                        {{-- User Header with Logout --}}
                        @if (true || $stats['is_sustaining_member'] ?? false)
                            <x-tabler-star-filled class="w-4 h-4 text-primary " />
                        @endif
                    </h2>
                    <div>
                        {{ \App\Models\User::me()->email }}
                    </div>
                </div>

                {{-- Logout Icon Button --}}
                <form action="{{ filament()->getLogoutUrl() }}" method="post">
                    @csrf
                    <x-filament::icon-button icon="tabler-logout" color="gray" tag="button" type="submit"
                        tooltip="{{ __('filament-panels::widgets/account-widget.actions.logout.label') }}" />
                </form>
            </div>
            {{-- Action Buttons --}}
            <div class="flex-wrap sm:flex-nowrap flex flex-row md:flex-col gap-2">
                <a href="{{ \App\Filament\Pages\MyProfile::getUrl() }}"
                    class="grow flex items-center gap-1.5 px-3 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                    <x-tabler-user class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">Profile</span>
                </a>
                <a href="{{ \App\Filament\Pages\MyMembership::getUrl() }}"
                    class="grow flex items-center gap-1.5 px-3 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                    <x-tabler-star class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">Membership</span>
                </a>

                <a href="{{ \App\Filament\Pages\MyAccount::getUrl() }}"
                    class="grow flex items-center gap-1.5 px-3 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                    <x-tabler-settings class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">Settings</span>
                </a>
            </div>
        </div>

        {{-- Quick Action Cards (2x2 grid on medium+) --}}
        @foreach ($actions as $action)
            <a href="{{ $action['url'] }}"
                class="flex flex-col items-center justify-center gap-3 p-6 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 hover:shadow-sm transition-all duration-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750">
                @php
                    $colorClasses = match ($action['color']) {
                        'primary' => 'text-primary-500',
                        'success' => 'text-green-500',
                        'warning' => 'text-amber-500',
                        'danger' => 'text-red-500',
                        'info' => 'text-blue-500',
                        default => 'text-gray-500',
                    };
                @endphp
                <x-dynamic-component :component="$action['icon']" class="w-12 h-12 {{ $colorClasses }}" />
                <h3 class="text-sm font-medium text-gray-900 dark:text-white text-center">
                    {{ $action['label'] }}
                </h3>
            </a>
        @endforeach
    </div>
</x-filament-widgets::widget>
