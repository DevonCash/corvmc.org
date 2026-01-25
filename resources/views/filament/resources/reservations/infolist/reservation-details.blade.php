@php
    /** @var \CorvMC\SpaceManagement\Models\Reservation $record */
@endphp

<div class="space-y-6">
    {{-- Primary Info: WHO, WHEN, COST --}}
    <div class="grid grid-cols-2 gap-6">
        {{-- Event (if applicable) --}}
        @if ($record instanceof \App\Models\EventReservation && $record->reservable)
            <div class="col-span-2">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 flex items-center gap-2">
                    Event
                    <x-filament::icon icon="tabler-calendar-event" class="h-4 w-4" />
                </dt>
                <dd class="mt-1">
                    <a href="{{ \App\Filament\Resources\Events\EventResource::getUrl('view', ['record' => $record->reservable]) }}"
                        target="_blank"
                        class="text-2xl font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
                        {{ $record->reservable->title }}
                    </a>
                </dd>
            </div>
        @endif

        {{-- Member --}}
        <div class="col-span-2">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 flex items-center gap-2">
                Member
                @if ($record->getResponsibleUser()?->isSustainingMember())
                    <x-filament::icon icon="tabler-heart" class="h-4 w-4" />
                @endif
            </dt>
            <dd class="mt-1 flex items-center gap-3">
                @php
                    $responsibleUser = $record->getResponsibleUser();
                @endphp
                @if ($responsibleUser)
                    <x-filament::avatar :src="$responsibleUser->getFilamentAvatarUrl()" :name="$responsibleUser->name" size="lg" />
                    <div class="grow">
                        <a href="{{ \App\Filament\Resources\Users\UserResource::getUrl('view', ['record' => $responsibleUser]) }}"
                            target="_blank"
                            class="text-xl font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
                            {{ $responsibleUser->name }}
                        </a>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $responsibleUser->email }}</p>
                    </div>
                @else
                    <span class="text-sm text-gray-500 dark:text-gray-400">No responsible user</span>
                @endif
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
            <dd class="mt-1 flex items-center gap-2">
                <div>
                    @if (!$record->reserved_at || !$record->reserved_until)
                        <span class="text-lg font-semibold">TBD</span>
                    @elseif ($record->reserved_at->isSameDay($record->reserved_until))
                        {{-- Single day --}}
                        <div class="text-lg font-semibold">{{ $record->reserved_at->format('M j, Y') }}</div>
                        <div class="text-base text-gray-700 dark:text-gray-300">
                            {{ $record->reserved_at->format('g:i A') }} - {{ $record->reserved_until->format('g:i A') }}
                        </div>
                    @else
                        {{-- Multiple days --}}
                        <div class="text-lg font-semibold">{{ $record->reserved_at->format('M j, Y g:i A') }}</div>
                        <div class="text-base text-gray-700 dark:text-gray-300">
                            to {{ $record->reserved_until->format('M j, Y g:i A') }}</div>
                    @endif
                </div>

                @if ($record->is_recurring)
                    <x-filament::icon icon="tabler-repeat" class="size-6 text-gray-500" />
                @endif
            </dd>
        </div>

        {{-- Cost & Payment --}}
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 flex items-center gap-2">
                Cost & Payment
                @if ($record->charge)
                    <x-filament::icon :icon="$record->charge->status->getIcon()" :class="'size-4 text-' . $record->charge->status->getColor() . '-500'" />
                @else
                    <x-filament::icon icon="tabler-currency-dollar" class="size-4 text-gray-500" />
                @endif
            </dt>
            <dd class="mt-1 flex items-center gap-4">
                <div>
                    <div class="text-lg font-semibold">
                        @if (!$record->charge?->net_amount || $record->charge->net_amount->isZero())
                            Free
                        @else
                            {{ $record->charge->net_amount->formatTo('en_US') }}
                        @endif
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        @if ($record->free_hours_used > 0)
                            {{ number_format($record->free_hours_used ?? 0, 1) }} hrs free
                        @else
                            {{ number_format($record->duration, 1) }} hours
                        @endif
                    </div>
                </div>
                @if ($record->charge?->net_amount?->isPositive())
                    <div class="flex items-center gap-3">
                        <div class="h-8 w-px bg-gray-300 dark:bg-gray-600"></div>
                        <x-filament::badge :color="$record->charge->status->getColor()" size="lg">
                            {{ $record->charge->status->getLabel() }}
                        </x-filament::badge>
                        @if ($record->charge->payment_method || $record->charge->paid_at)
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                @if ($record->charge->payment_method)
                                    {{ ucfirst($record->charge->payment_method) }}
                                @endif
                                @if ($record->charge->paid_at)
                                    @if ($record->charge->payment_method) &middot; @endif
                                    {{ $record->charge->paid_at->format('M j, Y') }}
                                @endif
                            </span>
                        @endif
                    </div>
                @endif
            </dd>
        </div>

        {{-- Notes (if filled) --}}
        @if (filled($record->notes))
            <div class="col-span-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Notes</dt>
                <dd class="mt-1 text-base text-gray-900 dark:text-gray-100">{{ $record->notes }}</dd>
            </div>
        @endif

        {{-- Payment Notes (if filled) --}}
        @if (filled($record->charge?->notes))
            <div class="col-span-2 @if(!filled($record->notes)) pt-2 border-t border-gray-200 dark:border-gray-700 @endif">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Payment Notes</dt>
                <dd class="mt-1 text-base text-gray-900 dark:text-gray-100">{{ $record->charge->notes }}</dd>
            </div>
        @endif
    </div>

    {{-- Secondary Metadata --}}
    <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
        <div class="grid grid-cols-3 gap-4 text-xs text-gray-500 dark:text-gray-500">
            <div>
                <dt class="font-medium">Type</dt>
                <dd class="mt-0.5">{{ class_basename($record->type) }}</dd>
            </div>
            <div class="col-span-2">
                <dt class="font-medium">Created</dt>
                <dd class="mt-0.5">{{ $record->created_at->format('M j, Y g:i A') }}</dd>
            </div>
        </div>
    </div>
</div>
