@php
    /** @var \App\Models\RehearsalReservation $record */
@endphp

<div class="space-y-6">
    {{-- Primary Info: WHEN, HOW LONG, HOW MUCH --}}
    <div class="grid grid-cols-2 gap-6">
        {{-- Date & Time --}}
        <div class="col-span-2">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 flex items-center gap-2">
                When
                <x-filament::icon icon="tabler-calendar-clock" class="h-4 w-4" />
            </dt>
            <dd class="mt-1">
                @if (!$record->reserved_at || !$record->reserved_until)
                    <span class="text-lg font-semibold">TBD</span>
                @elseif ($record->reserved_at->isSameDay($record->reserved_until))
                    {{-- Single day --}}
                    <div class="text-2xl font-bold">{{ $record->reserved_at->format('M j, Y') }}</div>
                    <div class="text-lg text-gray-700 dark:text-gray-300">
                        {{ $record->reserved_at->format('g:i A') }} - {{ $record->reserved_until->format('g:i A') }}
                    </div>
                @else
                    {{-- Multi-day --}}
                    <div class="text-lg font-semibold">
                        {{ $record->reserved_at->format('M j, Y g:i A') }} -<br>
                        {{ $record->reserved_until->format('M j, Y g:i A') }}
                    </div>
                @endif
            </dd>
        </div>

        {{-- Duration --}}
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 flex items-center gap-2">
                Duration
                <x-filament::icon icon="tabler-clock-hour-4" class="h-4 w-4" />
            </dt>
            <dd class="mt-1">
                <span class="text-xl font-semibold">{{ $record->duration }} hours</span>
            </dd>
        </div>

        {{-- Status --}}
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 flex items-center gap-2">
                Status
                <x-filament::icon icon="tabler-info-circle" class="h-4 w-4" />
            </dt>
            <dd class="mt-1">
                <x-filament::badge :color="$record->status->getColor()" size="lg">
                    {{ $record->status->getLabel() }}
                </x-filament::badge>
            </dd>
        </div>
    </div>

    {{-- Cost Breakdown --}}
    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
        <h3 class="text-lg font-semibold mb-4">Cost</h3>
        <div class="space-y-3">
            @if ($record->free_hours_used > 0)
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Free Hours Used</span>
                    <span class="font-medium">{{ $record->free_hours_used }} hours</span>
                </div>
            @endif

            <div class="flex justify-between items-center text-lg font-semibold border-t border-gray-200 dark:border-gray-700 pt-3">
                <span>Total Cost</span>
                <span>{{ $record->cost_display }}</span>
            </div>

            @if ($record->cost->isPositive())
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Payment Status</span>
                    <x-filament::badge :color="$record->payment_status->getColor()">
                        {{ $record->payment_status->getLabel() }}
                    </x-filament::badge>
                </div>
            @endif
        </div>
    </div>

    {{-- Notes --}}
    @if ($record->notes)
        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
            <h3 class="text-lg font-semibold mb-2">Your Notes</h3>
            <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $record->notes }}</div>
        </div>
    @endif

    {{-- Additional Info (for scheduled reservations) --}}
    @if ($record->status->isScheduled())
        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <div class="flex gap-3">
                    <x-filament::icon icon="tabler-info-circle" class="h-5 w-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                    <div class="text-sm text-blue-900 dark:text-blue-100">
                        <p class="font-semibold mb-1">Your reservation is scheduled</p>
                        <p>Your time slot and credits are locked in. We'll send you a reminder 3 days before to confirm.</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Recurring info --}}
    @if ($record->isRecurring())
        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
            <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                <x-filament::icon icon="tabler-repeat" class="h-4 w-4" />
                <span>This is part of a recurring reservation series</span>
            </div>
        </div>
    @endif

    {{-- Reservation ID for support reference --}}
    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
        <div class="text-xs text-gray-500 dark:text-gray-500">
            #{{ $record->id }}
        </div>
    </div>
</div>
