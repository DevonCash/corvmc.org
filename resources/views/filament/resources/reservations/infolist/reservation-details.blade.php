@php
    /** @var \App\Models\Reservation $record */
@endphp

<div class="space-y-6">
    {{-- Primary Info: WHO, WHEN, STATUS --}}
    <div class="grid grid-cols-3 gap-6">
        {{-- Member --}}
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 flex items-center gap-2">
                Member
                @if ($record->getResponsibleUser()->isSustainingMember())
                    <x-filament::icon icon="tabler-heart" class="h-4 w-4" />
                @endif
            </dt>
            <dd class=" mt-1 flex items-center gap-2">
                <x-filament::avatar :src="$record->user->getFilamentAvatarUrl()" :name="$record->user->name" size="lg" />
                <div>
                    <a target="_blank"
                        class="text-lg font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
                        {{ $record->user->name }}
                    </a>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $record->user->email }}</p>
                </div>
            </dd>
        </div>

        {{-- Time --}}
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 flex items-center gap-2">
                Time
                <x-filament::icon :icon="match ($record->status) {
                    'confirmed' => 'tabler-clock-check',
                    'cancelled' => 'tabler-clock-x',
                    'pending' => 'tabler-clock-question',
                    default => 'tabler-clock',
                }" :class="'size-4 ' .
                    match ($record->status) {
                        'confirmed' => 'text-success-500',
                        'cancelled' => 'text-danger-500',
                        'pending' => 'text-warning-500',
                        default => 'text-gray-500',
                    }" />
            </dt>
            <dd class="mt-1 flex items-center gap-2 ">
                <div>
                    @if (!$record->reserved_at || !$record->reserved_until)
                        <span>TBD</span>
                    @elseif ($record->reserved_at->isSameDay($record->reserved_until))
                        {{-- Single day --}}
                        <div class="text-lg">{{ $record->reserved_at->format('M j, Y') }}</div>
                        <div class="text-gray-600 dark:text-gray-400">
                            {{ $record->reserved_at->format('g:i A') }} - {{ $record->reserved_until->format('g:i A') }}
                        </div>
                    @else
                        {{-- Multiple days --}}
                        <div>{{ $record->reserved_at->format('M j, Y g:i A') }}</div>
                        <div class="text-gray-600 dark:text-gray-400">
                            {{ $record->reserved_until->format('M j, Y g:i A') }}</div>
                    @endif
                </div>

                @if ($record->is_recurring)
                    <x-filament::icon icon="tabler-repeat" class="size-6" />
                @endif
            </dd>
        </div>

        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 flex items-center gap-2">
                Cost
                <x-filament::icon :icon="match ($record->payment_status) {
                    \App\Enums\PaymentStatus::Paid => match ($record->payment_method) {
                        'credit_card' => 'tabler-credit-card',
                        'cash' => 'tabler-cash',
                    },
                    \App\Enums\PaymentStatus::Unpaid => $record->reserved_until->isPast()
                        ? 'tabler-question-circle'
                        : 'tabler-alert-circle',
                    default => 'tabler-currency-dollar',
                }" :class="'size-4 ' .
                    match ($record->status) {
                        \App\Enums\ReservationStatus::Confirmed => 'text-success-500',
                        \App\Enums\ReservationStatus::Cancelled => 'text-danger-500',
                        \App\Enums\ReservationStatus::Pending => 'text-warning-500',
                        default => 'text-gray-500',
                    }" />
            </dt>
            <dd class="mt-1">
                <div class="text-lg">{{ $record->cost_display }}</div>
                <div>
                    @if ($record->free_hours_used > 0)
                        ({{ number_format($record->free_hours_used ?? 0, 1) }} hrs free)
                    @else
                        {{ number_format($record->duration, 1) }} hours
                    @endif
                </div>
            </dd>
        </div>
    </div>


    {{-- Payment Details (collapsible) --}}
    @if ($record->cost->isPositive())
        <x-filament::section collapsible collapsed heading="Payment Details">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Payment Status</dt>
                    <dd class="mt-1">
                        <x-filament::badge :color="$record->payment_status_badge['color']">
                            {{ $record->payment_status_badge['label'] }}
                        </x-filament::badge>
                    </dd>
                </div>

                @if ($record->free_hours_used > 0)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Free Hours</dt>
                        <dd class="mt-1 text-sm text-success-600 dark:text-success-400">
                            {{ number_format($record->free_hours_used, 1) }} hrs
                        </dd>
                    </div>
                @endif

                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Payment Method</dt>
                    <dd class="mt-1 text-sm">{{ $record->payment_method ? ucfirst($record->payment_method) : '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Paid</dt>
                    <dd class="mt-1 text-sm">
                        {{ $record->paid_at ? $record->paid_at->format('M j, Y g:i A') : '—' }}
                    </dd>
                </div>

                @if (filled($record->payment_notes))
                    <div class="col-span-2">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Payment Notes</dt>
                        <dd class="mt-1 text-sm">{{ $record->payment_notes }}</dd>
                    </div>
                @endif
            </div>
        </x-filament::section>
    @endif

    {{-- Additional Details (collapsible) --}}
    <x-filament::section collapsible collapsed heading="Additional Details">
        <div class="grid grid-cols-2 gap-4">
            @if ($record->production_id)
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Production</dt>
                    <dd class="mt-1 text-sm">{{ $record->production->title }}</dd>
                </div>
            @endif

            @if ($record->is_recurring)
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Recurring</dt>
                    <dd class="mt-1 text-sm">
                        <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5 text-success-500" />
                    </dd>
                </div>
            @endif

            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Type</dt>
                <dd class="mt-1 text-sm">{{ class_basename($record->type) }}</dd>
            </div>

            @if (filled($record->notes))
                <div class="col-span-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Notes</dt>
                    <dd class="mt-1 text-sm">{{ $record->notes }}</dd>
                </div>
            @endif

            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                <dd class="mt-1 text-sm">
                    {{ $record->created_at->format('M j, Y g:i A') }}
                    <span class="text-gray-500">({{ $record->created_at->diffForHumans() }})</span>
                </dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Updated</dt>
                <dd class="mt-1 text-sm">
                    {{ $record->updated_at->format('M j, Y g:i A') }}
                    <span class="text-gray-500">({{ $record->updated_at->diffForHumans() }})</span>
                </dd>
            </div>
        </div>
    </x-filament::section>
</div>
