<x-public.layout :title="'Tickets Purchased | ' . $event->title">
    <div class="container mx-auto px-4 py-12 max-w-2xl">
        {{-- Success Icon --}}
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-success/20 text-success mb-4">
                <x-tabler-circle-check class="size-12" />
            </div>
            <h1 class="text-3xl font-bold">
                {{ $order->quantity }} {{ Str::plural('Ticket', $order->quantity) }} Purchased
            </h1>
            <p class="text-base-content/70 mt-2">Your order has been confirmed</p>
        </div>

        {{-- Order Summary Card --}}
        <div class="card bg-base-200 mb-6">
            <div class="card-body">
                <h2 class="card-title text-lg">{{ $event->title }}</h2>
                <div class="flex flex-col gap-2 text-sm">
                    <div class="flex items-center gap-2">
                        <x-tabler-calendar class="size-5 text-primary" />
                        <span>{{ $event->start_datetime->format('l, F j, Y') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-tabler-clock class="size-5 text-primary" />
                        <span>{{ $event->start_datetime->format('g:i A') }}</span>
                    </div>
                    @if($event->venue_name)
                        <div class="flex items-center gap-2">
                            <x-tabler-map-pin class="size-5 text-primary" />
                            <span>{{ $event->venue_name }}</span>
                        </div>
                    @endif
                </div>

                <div class="divider my-2"></div>

                <div class="flex justify-between items-center">
                    <span>{{ $order->quantity }} x Ticket</span>
                    <span class="font-semibold">${{ number_format($order->total->getAmount()->toFloat(), 2) }}</span>
                </div>
            </div>
        </div>

        {{-- Email Confirmation Notice --}}
        <div class="alert mb-6">
            <x-tabler-mail class="size-6" />
            <div>
                <p class="font-semibold">Confirmation email sent</p>
                <p class="text-sm">Your tickets have been sent to <span class="font-medium">{{ $order->email }}</span></p>
            </div>
        </div>

        {{-- Guest Account Creation CTA --}}
        @if($isGuest)
            <div class="card bg-primary/10 border border-primary/20 mb-6">
                <div class="card-body">
                    <h3 class="card-title text-lg">
                        <x-tabler-user-plus class="size-6" />
                        Create an Account
                    </h3>
                    <p class="text-sm text-base-content/70">
                        Join Corvallis Music Collective to access member benefits:
                    </p>
                    <ul class="text-sm space-y-1 mt-2">
                        <li class="flex items-center gap-2">
                            <x-tabler-check class="size-4 text-success" />
                            View and manage your tickets in one place
                        </li>
                        <li class="flex items-center gap-2">
                            <x-tabler-check class="size-4 text-success" />
                            Get notified about upcoming events
                        </li>
                        <li class="flex items-center gap-2">
                            <x-tabler-check class="size-4 text-success" />
                            Access member-only pricing as a sustaining member
                        </li>
                    </ul>
                    <div class="card-actions mt-4">
                        <a href="{{ route('filament.member.auth.register', ['email' => $order->email]) }}" class="btn btn-primary">
                            <x-tabler-user-plus class="size-5" />
                            Create Free Account
                        </a>
                    </div>
                </div>
            </div>
        @endif

        {{-- Action Buttons --}}
        <div class="flex flex-col sm:flex-row gap-3">
            <a href="{{ route('events.show', $event) }}" class="btn btn-outline flex-1">
                <x-tabler-arrow-left class="size-5" />
                Back to Event
            </a>
            <a href="{{ route('events.index') }}" class="btn btn-ghost flex-1">
                Browse More Events
            </a>
            @auth
                <a href="{{ route('filament.member.pages.my-tickets') }}" class="btn btn-primary flex-1">
                    <x-tabler-ticket class="size-5" />
                    View My Tickets
                </a>
            @endauth
        </div>
    </div>
</x-public.layout>
