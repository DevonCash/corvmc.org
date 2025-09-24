<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Quick Actions
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- New Reservation Card -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        Book Practice Time
                    </h3>
                    <x-tabler-calendar-plus class="w-5 h-5 text-gray-400" />
                </div>

                @if($canBookNow)
                    <div class="mb-3">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                            <x-tabler-calendar-smile class="w-3 h-3 mr-1" />
                            Available now
                        </span>
                    </div>
                @else
                    <div class="mb-3">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                            <x-tabler-calendar-user class="w-3 h-3 mr-1" />
                            Currently occupied
                        </span>
                    </div>
                @endif

                <x-filament::button
                    href="{{ route('filament.member.resources.reservations.create') }}"
                    size="sm"
                    color="primary"
                >
                    <x-tabler-calendar-plus class="w-4 h-4 mr-2" />
                    New Reservation
                </x-filament::button>
            </div>

            <!-- Upcoming Reservations Card -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        Your Upcoming Sessions
                    </h3>
                    <x-tabler-calendar-clock class="w-5 h-5 text-gray-400" />
                </div>

                @if($upcomingReservations->count() > 0)
                    <div class="space-y-2 mb-3">
                        @foreach($upcomingReservations as $reservation)
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-600 dark:text-gray-400">
                                    {{ $reservation->reserved_at->format('M j, g:i A') }}
                                </span>
                                <span class="px-2 py-1 rounded text-xs {{ $reservation->status === 'confirmed' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' }}">
                                    {{ ucfirst($reservation->status) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                        No upcoming reservations
                    </p>
                @endif

                <x-filament::button
                    href="{{ route('filament.member.resources.reservations.index') }}"
                    size="sm"
                    color="gray"
                    outlined
                >
                    View All
                </x-filament::button>
            </div>

            <!-- Pending Actions Card -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        Needs Attention
                    </h3>
                    <x-tabler-alert-triangle class="w-5 h-5 text-gray-400" />
                </div>

                @if($pendingConfirmations->count() > 0)
                    <div class="mb-3">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                            <x-tabler-calendar-question class="w-3 h-3 mr-1" />
                            {{ $pendingConfirmations->count() }} pending confirmation{{ $pendingConfirmations->count() > 1 ? 's' : '' }}
                        </span>
                    </div>

                    <div class="space-y-1 mb-3">
                        @foreach($pendingConfirmations->take(2) as $reservation)
                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                {{ $reservation->reserved_at->format('M j, g:i A') }}
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mb-3">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                            <x-tabler-calendar-check class="w-3 h-3 mr-1" />
                            All confirmed
                        </span>
                    </div>
                @endif

                @if($pendingConfirmations->count() > 0)
                    <x-filament::button
                        href="{{ route('filament.member.resources.reservations.index') }}?filter[status]=pending"
                        size="sm"
                        color="warning"
                    >
                        Review Pending
                    </x-filament::button>
                @else
                    <x-filament::button
                        href="{{ route('filament.member.resources.reservations.calendar') }}"
                        size="sm"
                        color="gray"
                        outlined
                    >
                        View Calendar
                    </x-filament::button>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
