@php
    $affectedReservations = $getState();
    $reservations = is_string($affectedReservations) ? json_decode($affectedReservations, true) : $affectedReservations;
    $hasReservations = !empty($reservations) && count($reservations) > 0;
@endphp

<div class="space-y-3">
    @if ($hasReservations)
        <div class="rounded-lg bg-warning-50 dark:bg-warning-950 p-4 ring-1 ring-warning-200 dark:ring-warning-800">
            <div class="flex items-start gap-3">
                <x-filament::icon
                    icon="tabler-alert-triangle"
                    class="h-5 w-5 text-warning-500 mt-0.5"
                />
                <div class="flex-1">
                    <p class="text-sm font-medium text-warning-800 dark:text-warning-200">
                        {{ count($reservations) }} {{ Str::plural('reservation', count($reservations)) }} will be affected
                    </p>
                    <p class="text-xs text-warning-600 dark:text-warning-400 mt-1">
                        These reservations overlap with the closure period.
                    </p>
                </div>
            </div>
        </div>

        <div class="rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
            <table class="w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Member
                        </th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Date
                        </th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Time
                        </th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Status
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @foreach ($reservations as $reservation)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                {{ $reservation['user_name'] }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                {{ $reservation['date'] }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                {{ $reservation['time_range'] }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset
                                    @if($reservation['status'] === 'Confirmed')
                                        bg-success-50 dark:bg-success-400/10 text-success-700 dark:text-success-400 ring-success-600/20 dark:ring-success-400/30
                                    @elseif($reservation['status'] === 'Scheduled' || $reservation['status'] === 'Reserved')
                                        bg-warning-50 dark:bg-warning-400/10 text-warning-700 dark:text-warning-400 ring-warning-600/20 dark:ring-warning-400/30
                                    @else
                                        bg-gray-50 dark:bg-gray-400/10 text-gray-700 dark:text-gray-400 ring-gray-600/20 dark:ring-gray-400/30
                                    @endif
                                ">
                                    {{ $reservation['status'] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="rounded-lg bg-success-50 dark:bg-success-950 p-4 ring-1 ring-success-200 dark:ring-success-800">
            <div class="flex items-center gap-3">
                <x-filament::icon
                    icon="tabler-circle-check"
                    class="h-5 w-5 text-success-500"
                />
                <p class="text-sm font-medium text-success-800 dark:text-success-200">
                    No reservations will be affected by this closure
                </p>
            </div>
        </div>
    @endif
</div>
