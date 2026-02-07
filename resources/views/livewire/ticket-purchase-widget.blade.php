<div class="ticket-purchase-widget">
    {{-- User Profile (logged in) or Guest Info --}}
    @auth
        <div class="mb-6 rounded-lg bg-base-200 p-4">
            <div class="flex items-center gap-3">
                <img src="{{ auth()->user()->getFilamentAvatarUrl() }}"
                     alt="{{ auth()->user()->name }}"
                     class="size-12 rounded-full" />
                <div class="flex-1">
                    <p class="font-semibold">{{ auth()->user()->name }}</p>
                    <p class="text-sm text-base-content/70">{{ auth()->user()->email }}</p>
                </div>
                <button type="button"
                        class="link link-primary text-sm"
                        onclick="document.getElementById('logout-modal').showModal()">
                    Not you?
                </button>
            </div>
        </div>

        {{-- Logout Confirmation Modal --}}
        <dialog id="logout-modal" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Log out?</h3>
                <p class="py-4">You'll need to log in again to get member pricing.</p>
                <div class="modal-action">
                    <form method="dialog">
                        <button class="btn">Cancel</button>
                    </form>
                    <form action="{{ route('filament.member.auth.logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-primary">Log out</button>
                    </form>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button>close</button>
            </form>
        </dialog>
    @endauth

    {{-- NOTAFLOF Notice --}}
    <div class="mb-6 rounded-lg bg-success/10 border border-success/30 p-4">
        <div class="flex gap-3">
            <x-tabler-heart-handshake class="size-6 text-success flex-shrink-0" />
            <div>
                <p class="font-semibold text-success">No One Turned Away For Lack of Funds</p>
                <p class="text-sm text-base-content/70 mt-1">
                    Can't afford a ticket? Email us at <a href="mailto:info@corvallismusic.org" class="link">info@corvallismusic.org</a> and we'll work something out.
                </p>
            </div>
        </div>
    </div>

    {{-- Pricing Display --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <span class="text-lg">Ticket Price</span>
            <div class="text-right">
                @if($this->hasDiscount())
                    <span class="text-sm text-base-content/50 line-through">${{ number_format($this->getBasePrice() / 100, 2) }}</span>
                    <span class="text-lg font-bold text-success">${{ number_format($this->getUnitPrice() / 100, 2) }}</span>
                    <span class="badge badge-success badge-sm ml-1">{{ $this->getDiscountPercent() }}% off</span>
                @else
                    <span class="text-lg font-bold">${{ number_format($this->getUnitPrice() / 100, 2) }}</span>
                @endif
            </div>
        </div>

        @if($this->hasDiscount())
            <p class="mt-1 text-sm text-success">Sustaining member discount applied</p>
        @elseif(!auth()->check())
            <p class="mt-1 text-sm text-base-content/70">
                <a href="{{ route('filament.member.auth.login') }}" class="link link-primary">Log in</a> for member pricing
            </p>
        @endif
    </div>

    {{-- Sold Out State --}}
    @if($event->isSoldOut())
        <div class="alert alert-warning">
            <x-tabler-ticket-off class="size-6" />
            <span>This event is sold out.</span>
        </div>
    @else
        {{-- Purchase Form --}}
        <form wire:submit.prevent="purchase">
            {{ $this->form }}

            {{-- Order Summary --}}
            <div class="mt-6 rounded-lg bg-base-200 p-4">
                <h4 class="font-semibold">Order Summary</h4>
                <div class="mt-2 space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span>{{ $quantity }} x Ticket</span>
                        <span>${{ number_format(($this->getBasePrice() * $quantity) / 100, 2) }}</span>
                    </div>
                    @if($this->hasDiscount())
                        <div class="flex justify-between text-success">
                            <span>Member Discount ({{ $this->getDiscountPercent() }}%)</span>
                            <span>-${{ number_format((($this->getBasePrice() - $this->getUnitPrice()) * $quantity) / 100, 2) }}</span>
                        </div>
                    @endif
                    @if($coversFees)
                        <div class="flex justify-between text-base-content/70">
                            <span>Processing Fees</span>
                            <span>+${{ number_format($this->calculateFees() / 100, 2) }}</span>
                        </div>
                    @endif
                    <div class="divider my-2"></div>
                    <div class="flex justify-between text-lg font-bold">
                        <span>Total</span>
                        <span>${{ number_format($this->getTotal() / 100, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Remaining Tickets --}}
            @if($event->getTicketsRemaining() !== null)
                <p class="mt-4 text-center text-sm text-base-content/70">
                    @if($event->getTicketsRemaining() <= 10)
                        <span class="text-warning font-semibold">Only {{ $event->getTicketsRemaining() }} tickets remaining!</span>
                    @else
                        {{ $event->getTicketsRemaining() }} tickets remaining
                    @endif
                </p>
            @endif

            {{-- Submit Button --}}
            <div class="mt-6" wire:ignore.self>
                <button
                    type="button"
                    wire:click="purchase"
                    class="btn btn-primary btn-lg w-full"
                    style="min-height: 3.5rem;"
                    wire:loading.attr="disabled"
                    wire:loading.class="btn-disabled"
                    wire:target="purchase"
                >
                    <span wire:loading.remove wire:target="purchase" class="flex items-center gap-2">
                        <x-tabler-ticket class="size-5 flex-shrink-0" />
                        Buy {{ $quantity }} Ticket{{ $quantity > 1 ? 's' : '' }}
                    </span>
                    <span wire:loading wire:target="purchase" class="flex items-center gap-2">
                        <span class="loading loading-spinner"></span>
                        Processing...
                    </span>
                </button>
            </div>

            {{-- Guest Checkout Note --}}
            @if(!auth()->check())
                <p class="mt-4 text-center text-xs text-base-content/50">
                    By purchasing, you agree to our terms. A confirmation email will be sent to your email address.
                </p>
            @endif
        </form>
    @endif

    <x-filament-actions::modals />
</div>
