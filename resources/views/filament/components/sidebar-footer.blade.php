<div class="fi-sidebar-footer mt-auto">
    {{-- Back to Personal Dashboard --}}
    @if (filament()->getCurrentPanel()->getId() !== 'member')
        <x-filament::button outlined tag='a' href="{{ route('filament.member.pages.member-dashboard') }}">
            <x-tabler-arrow-left class="h-5 w-5" />
            <span>Back to Member Dashboard</span>
        </x-filament::button>
    @endif
    <hr class='border-gray-200 dark:border-gray-700' />
    <a href="{{ route('filament.member.pages.account') }}"
        class="flex items-center gap-3 p-4 group hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors rounded-lg -mx-2 border border-transparent hover:border-gray-200 dark:hover:border-gray-600">
        <!-- User Avatar -->
        <div class="flex-shrink-0">
            @if (auth()->user()->getFilamentAvatarUrl())
                <img src="{{ auth()->user()->getFilamentAvatarUrl() }}" alt="{{ auth()->user()->name }}"
                    class="w-10 h-10 rounded-full object-cover">
            @else
                <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
                    <span class="text-primary-600 dark:text-primary-400 font-medium text-sm">
                        {{ substr(auth()->user()->name, 0, 1) }}
                    </span>
                </div>
            @endif
        </div>

        <!-- User Info -->
        <div class="flex-1 min-w-0">
            <p
                class="text-sm font-medium text-gray-900 dark:text-white truncate group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                {{ auth()->user()->name }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                {{ auth()->user()->email }}
            </p>
        </div>

        <!-- Edit Icon -->
        <div class="flex-shrink-0">
            <x-tabler-user-cog
                class="size-5 text-gray-400 dark:text-gray-500 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors" />
        </div>
    </a>

</div>
