<x-filament-panels::page>
    <div class="space-y-3">
        {{-- Upcoming Reservations (Static Blade) --}}
        @include('filament.partials.upcoming-reservations', [
            'reservations' => $this->getUpcomingReservations()
        ])

        {{-- Quick Actions (Static Blade) --}}
        @include('filament.partials.quick-actions', [
            'actions' => $this->getQuickActions(),
            'user' => filament()->auth()->user(),
            'stats' => $this->getUserStats()
        ])

        {{-- Upcoming Events (Static Blade) --}}
        @include('filament.partials.upcoming-events', [
            'events' => $this->getUpcomingEvents()
        ])
    </div>
</x-filament-panels::page>
