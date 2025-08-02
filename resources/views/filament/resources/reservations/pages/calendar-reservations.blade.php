<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Weekly Practice Space Schedule
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Detailed weekly view of reservations and productions. Business hours are 9 AM - 10 PM daily.
                </p>
            </div>
            
            <div class="flex flex-wrap gap-4 mb-4">
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 bg-green-500 rounded"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Confirmed Reservations</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 bg-yellow-500 rounded"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Pending Reservations</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 bg-blue-500 rounded"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Productions</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 bg-purple-500 rounded"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Pre-Production</span>
                </div>
            </div>
        </div>
    </div>

    {{-- The header widgets (calendar) will automatically render above this content --}}
</x-filament-panels::page>