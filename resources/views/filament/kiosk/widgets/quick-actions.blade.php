<x-filament-widgets::widget>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Check In Member --}}
        <a href="{{ \App\Filament\Kiosk\Pages\PointOfSale::getUrl() }}"
           class="flex flex-col items-center justify-center p-8 bg-success-50 dark:bg-success-950 hover:bg-success-100 dark:hover:bg-success-900 rounded-xl border-2 border-success-600 transition-colors group">
            <x-filament::icon
                icon="tabler-cash-register"
                class="w-16 h-16 text-success-600 mb-3 group-hover:scale-110 transition-transform"
            />
            <span class="text-xl font-semibold text-success-900 dark:text-success-100">Point of Sale</span>
            <span class="text-sm text-success-700 dark:text-success-300 mt-1">Concessions or Merch</span>
        </a>

        {{-- Check Out Member --}}
        <a href="#"
           class="flex flex-col items-center justify-center p-8 bg-warning-50 dark:bg-warning-950 hover:bg-warning-100 dark:hover:bg-warning-900 rounded-xl border-2 border-warning-600 transition-colors group">
            <x-filament::icon
                icon="tabler-device-speaker"
                class="w-16 h-16 text-warning-600 mb-3 group-hover:scale-110 transition-transform"
            />
            <span class="text-xl font-semibold text-warning-900 dark:text-warning-100">Equipment</span>
            <span class="text-sm text-warning-700 dark:text-warning-300 mt-1">Equipment Checkout/Checkin</span>
        </a>

        {{-- Walk-In Reservation --}}
        <a href="{{ \App\Filament\Kiosk\Pages\WalkInReservation::getUrl() }}"
           class="flex flex-col items-center justify-center p-8 bg-primary-50 dark:bg-primary-950 hover:bg-primary-100 dark:hover:bg-primary-900 rounded-xl border-2 border-primary-600 transition-colors group">
            <x-filament::icon
                icon="tabler-calendar-plus"
                class="w-16 h-16 text-primary-600 mb-3 group-hover:scale-110 transition-transform"
            />
            <span class="text-xl font-semibold text-primary-900 dark:text-primary-100">Rehearsal</span>
            <span class="text-sm text-primary-700 dark:text-primary-300 mt-1">Schedule or Walk-In</span>
        </a>

        {{-- Find Reservation --}}
        <a href="{{ \App\Filament\Kiosk\Resources\ReservationResource::getUrl() }}"
           class="flex flex-col items-center justify-center p-8 bg-gray-50 dark:bg-gray-950 hover:bg-gray-100 dark:hover:bg-gray-900 rounded-xl border-2 border-gray-400 dark:border-gray-600 transition-colors group">
            <x-filament::icon
                icon="tabler-magnifying-glass"
                class="w-16 h-16 text-gray-600 dark:text-gray-400 mb-3 group-hover:scale-110 transition-transform"
            />
            <span class="text-xl font-semibold text-gray-900 dark:text-gray-100">Find</span>
            <span class="text-sm text-gray-700 dark:text-gray-300 mt-1">Search reservations</span>
        </a>
    </div>
</x-filament-widgets::widget>
