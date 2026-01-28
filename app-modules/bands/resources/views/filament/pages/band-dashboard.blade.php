<x-filament-panels::page>
    @php
        $band = $this->getBand();
        $stats = $this->getBandStats();
    @endphp

    <div class="space-y-6">
        {{-- Band Header --}}
        <div class="fi-section p-6">
            <div class="flex items-center gap-4">
                <img src="{{ $band->avatar_thumb_url }}"
                    alt="{{ $band->name }}"
                    class="w-20 h-20 rounded-lg object-cover">
                <div class="flex-1">
                    <h2 class="text-2xl font-bold text-gray-950 dark:text-white">{{ $band->name }}</h2>
                    @if($band->hometown)
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $band->hometown }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Stats Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="fi-section p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-primary-50 dark:bg-primary-900/20">
                        <x-heroicon-o-user-group class="w-6 h-6 text-primary-500" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-950 dark:text-white">{{ $stats['active_members'] }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Active Members</p>
                    </div>
                </div>
            </div>

            <div class="fi-section p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-warning-50 dark:bg-warning-900/20">
                        <x-heroicon-o-envelope class="w-6 h-6 text-warning-500" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-950 dark:text-white">{{ $stats['pending_invitations'] }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Pending Invitations</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-filament-panels::page>
