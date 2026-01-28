<x-filament-panels::page>
    <div class="space-y-8">
        {{-- Upcoming Events --}}
        <div>
            <h3 class="mb-4 text-xl font-semibold text-gray-900 dark:text-white">Upcoming Events</h3>

            @forelse($this->getUpcomingOrders() as $order)
                <x-filament::card class="mb-4">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div class="flex-1">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ $order->event->title }}
                            </h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $order->event->start_datetime->format('l, F j, Y') }}
                                at {{ $order->event->start_datetime->format('g:i A') }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $order->event->venue_name }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-2 md:items-end">
                            <x-filament::badge color="success">
                                {{ $order->quantity }} Ticket{{ $order->quantity > 1 ? 's' : '' }}
                            </x-filament::badge>
                        </div>
                    </div>

                    <hr class="my-4 border-gray-200 dark:border-gray-700">

                    <div class="space-y-2">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            Your Ticket Code{{ $order->tickets->count() > 1 ? 's' : '' }}:
                        </p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($order->tickets as $ticket)
                                <div class="flex items-center gap-2 rounded-lg bg-gray-100 px-3 py-2 dark:bg-gray-800">
                                    <span class="font-mono text-lg font-bold tracking-wider text-gray-900 dark:text-white">
                                        {{ $ticket->code }}
                                    </span>
                                    @if($ticket->status->value === 'checked_in')
                                        <x-filament::badge color="info" size="sm">Checked In</x-filament::badge>
                                    @elseif($ticket->status->value === 'cancelled')
                                        <x-filament::badge color="danger" size="sm">Cancelled</x-filament::badge>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Present this code at the door for entry
                        </p>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <x-filament::link
                            :href="route('events.show', $order->event)"
                            icon="tabler-calendar-event"
                            target="_blank"
                        >
                            View Event
                        </x-filament::link>
                    </div>
                </x-filament::card>
            @empty
                <x-filament::card>
                    <div class="py-6 text-center">
                        <x-tabler-ticket-off class="mx-auto mb-2 size-12 text-gray-400" />
                        <p class="text-gray-500 dark:text-gray-400">No upcoming event tickets.</p>
                        <x-filament::link
                            :href="route('events.index')"
                            class="mt-4"
                        >
                            Browse Events
                        </x-filament::link>
                    </div>
                </x-filament::card>
            @endforelse
        </div>

        {{-- Past Events --}}
        @php $pastOrders = $this->getPastOrders(); @endphp
        @if($pastOrders->isNotEmpty())
            <div>
                <h3 class="mb-4 text-xl font-semibold text-gray-900 dark:text-white">Past Events</h3>

                @foreach($pastOrders as $order)
                    <x-filament::card class="mb-2 opacity-75">
                        <div class="flex flex-col gap-2 py-2 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h4 class="font-semibold text-gray-900 dark:text-white">
                                    {{ $order->event->title }}
                                </h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $order->event->start_datetime->format('M j, Y') }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-filament::badge color="gray" size="sm">
                                    {{ $order->quantity }} Ticket{{ $order->quantity > 1 ? 's' : '' }}
                                </x-filament::badge>
                                @if($order->status->value === 'refunded')
                                    <x-filament::badge color="warning" size="sm">Refunded</x-filament::badge>
                                @endif
                            </div>
                        </div>
                    </x-filament::card>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
