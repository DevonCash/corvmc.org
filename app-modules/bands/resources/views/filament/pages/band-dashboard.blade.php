<x-filament-panels::page>
    @php
        $band = $this->getBand();
        $stats = $this->getBandStats();
        $reservations = $this->getUpcomingReservations();
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
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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

            <div class="fi-section p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-success-50 dark:bg-success-900/20">
                        <x-heroicon-o-calendar class="w-6 h-6 text-success-500" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-950 dark:text-white">{{ $stats['upcoming_reservations'] }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Upcoming Reservations</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Upcoming Reservations --}}
        @if($reservations->isNotEmpty())
            <div class="fi-section">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Upcoming Reservations</h3>
                </div>
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($reservations as $reservation)
                        <div class="p-4 flex items-center justify-between">
                            <div>
                                <p class="font-medium text-gray-950 dark:text-white">
                                    {{ $reservation->reserved_at->format('l, F j, Y') }}
                                </p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $reservation->reserved_at->format('g:i A') }} - {{ $reservation->reserved_until->format('g:i A') }}
                                </p>
                            </div>
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">
                                {{ $reservation->duration }} hours
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="fi-section p-6">
                <div class="text-center">
                    <x-heroicon-o-calendar-days class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-500" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No upcoming reservations</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Book a practice space for your band.</p>
                    <div class="mt-4">
                        <a href="{{ \App\Filament\Band\Resources\BandReservationsResource::getUrl('create') }}"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-500">
                            <x-heroicon-o-plus class="w-4 h-4 mr-2" />
                            Book Practice Space
                        </a>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
