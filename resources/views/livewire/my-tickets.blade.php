<div class="my-tickets">
    {{-- Upcoming Events --}}
    <div class="mb-8">
        <h3 class="mb-4 text-xl font-semibold">Upcoming Events</h3>

        @forelse($upcomingOrders as $order)
            <div class="card mb-4 bg-base-100 shadow-md">
                <div class="card-body">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div class="flex-1">
                            <h4 class="card-title text-lg">{{ $order->event->title }}</h4>
                            <p class="text-sm text-base-content/70">
                                {{ $order->event->start_datetime->format('l, F j, Y') }}
                                at {{ $order->event->start_datetime->format('g:i A') }}
                            </p>
                            <p class="text-sm text-base-content/70">
                                {{ $order->event->venue_name }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-2 md:items-end">
                            <span class="badge badge-success">
                                {{ $order->quantity }} Ticket{{ $order->quantity > 1 ? 's' : '' }}
                            </span>
                        </div>
                    </div>

                    {{-- Ticket Codes --}}
                    <div class="divider my-2"></div>
                    <div class="space-y-2">
                        <p class="text-sm font-semibold">Your Ticket Code{{ $order->tickets->count() > 1 ? 's' : '' }}:</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($order->tickets as $ticket)
                                <div class="flex items-center gap-2 rounded-lg bg-base-200 px-3 py-2">
                                    <span class="font-mono text-lg font-bold tracking-wider">{{ $ticket->code }}</span>
                                    @if($ticket->status->value === 'checked_in')
                                        <span class="badge badge-info badge-sm">Checked In</span>
                                    @elseif($ticket->status->value === 'cancelled')
                                        <span class="badge badge-error badge-sm">Cancelled</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <p class="text-xs text-base-content/50">Present this code at the door for entry</p>
                    </div>

                    {{-- Event Actions --}}
                    <div class="card-actions mt-4 justify-end">
                        <a href="{{ route('events.show', $order->event) }}" class="btn btn-sm btn-outline">
                            <x-tabler-calendar-event class="size-4" />
                            View Event
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-lg bg-base-200 p-6 text-center">
                <x-tabler-ticket-off class="mx-auto mb-2 size-12 text-base-content/50" />
                <p class="text-base-content/70">No upcoming event tickets.</p>
                <a href="{{ route('events.index') }}" class="btn btn-primary btn-sm mt-4">
                    Browse Events
                </a>
            </div>
        @endforelse
    </div>

    {{-- Past Events --}}
    <div>
        <button
            wire:click="togglePastEvents"
            class="btn btn-ghost btn-sm mb-4"
        >
            @if($showPastEvents)
                <x-tabler-chevron-up class="size-4" />
                Hide Past Events
            @else
                <x-tabler-chevron-down class="size-4" />
                Show Past Events
            @endif
        </button>

        @if($showPastEvents)
            @forelse($pastOrders as $order)
                <div class="card mb-4 bg-base-100 opacity-75 shadow-sm">
                    <div class="card-body py-4">
                        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h4 class="font-semibold">{{ $order->event->title }}</h4>
                                <p class="text-sm text-base-content/50">
                                    {{ $order->event->start_datetime->format('M j, Y') }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="badge badge-ghost badge-sm">
                                    {{ $order->quantity }} Ticket{{ $order->quantity > 1 ? 's' : '' }}
                                </span>
                                @if($order->status->value === 'refunded')
                                    <span class="badge badge-warning badge-sm">Refunded</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-center text-sm text-base-content/50">No past tickets found.</p>
            @endforelse
        @endif
    </div>
</div>
