<?php

use App\Livewire\TicketPurchaseWidget;
use App\Models\User;
use Carbon\Carbon;
use CorvMC\Events\Enums\TicketOrderStatus;
use CorvMC\Events\Facades\EventService;
use CorvMC\Events\Facades\TicketService;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Models\TicketOrder;
use CorvMC\Events\Models\Venue;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->seed(PermissionSeeder::class);

    $this->cmcVenue = Venue::create([
        'name' => 'Corvallis Music Collective',
        'address' => '420 SW Washington Ave',
        'city' => 'Corvallis',
        'state' => 'OR',
        'zip' => '97333',
        'is_cmc' => true,
    ]);

    $startDatetime = Carbon::now()->addDays(7)->setHour(19)->setMinute(0)->setSecond(0);
    $this->event = EventService::create([
        'title' => 'Rock Concert',
        'start_datetime' => $startDatetime,
        'end_datetime' => $startDatetime->copy()->addHours(3),
        'venue_id' => $this->cmcVenue->id,
        'ticketing_enabled' => true,
        'ticket_quantity' => 100,
    ]);
});

describe('TicketService: createOrder', function () {
    it('creates a pending ticket order for an authenticated user', function () {
        $user = User::factory()->create();

        $order = TicketService::createOrder($this->event, $user, 2);

        expect($order)->toBeInstanceOf(TicketOrder::class);
        expect($order->user_id)->toBe($user->id);
        expect($order->event_id)->toBe($this->event->id);
        expect($order->quantity)->toBe(2);
        expect($order->status)->toBe(TicketOrderStatus::Pending);
        expect($order->name)->toBe($user->name);
        expect($order->email)->toBe($user->email);
        expect($order->unit_price->getMinorAmount())->toBeGreaterThan(0);
        expect($order->subtotal->getMinorAmount())->toBe($order->unit_price->getMinorAmount() * 2);
        expect($order->total->getMinorAmount())->toBe($order->subtotal->getMinorAmount());
        expect($order->fees->getMinorAmount())->toBe(0);
    });

    it('applies sustaining member discount', function () {
        $user = User::factory()->create();
        $user->assignRole('sustaining member');

        $order = TicketService::createOrder($this->event, $user, 1);

        $basePrice = $this->event->getBaseTicketPrice()->getMinorAmount();
        $discountedPrice = $this->event->getTicketPriceForUser($user)->getMinorAmount();

        expect($discountedPrice)->toBeLessThan($basePrice);
        expect($order->unit_price->getMinorAmount())->toBe($discountedPrice);
    });

    it('includes fees when coversFees is true', function () {
        $user = User::factory()->create();

        $order = TicketService::createOrder($this->event, $user, 2, coversFees: true);

        expect($order->covers_fees)->toBeTrue();
        expect($order->fees->getMinorAmount())->toBeGreaterThan(0);
        expect($order->total->getMinorAmount())->toBe(
            $order->subtotal->getMinorAmount() + $order->fees->getMinorAmount()
        );
    });

    it('throws when not enough tickets available', function () {
        $user = User::factory()->create();
        $this->event->update(['ticket_quantity' => 2]);

        // Sell 2 tickets to exhaust supply
        $existingOrder = TicketOrder::factory()->completed()->create([
            'event_id' => $this->event->id,
            'quantity' => 2,
        ]);
        TicketService::generateTickets($existingOrder);

        TicketService::createOrder($this->event->fresh(), $user, 1);
    })->throws(RuntimeException::class);
});

describe('TicketService: guestCheckout', function () {
    it('creates a pending ticket order without a user', function () {
        $order = TicketService::guestCheckout(
            $this->event,
            quantity: 2,
            name: 'Jane Doe',
            email: 'jane@example.com',
        );

        expect($order)->toBeInstanceOf(TicketOrder::class);
        expect($order->user_id)->toBeNull();
        expect($order->name)->toBe('Jane Doe');
        expect($order->email)->toBe('jane@example.com');
        expect($order->event_id)->toBe($this->event->id);
        expect($order->quantity)->toBe(2);
        expect($order->status)->toBe(TicketOrderStatus::Pending);
        expect($order->unit_price->getMinorAmount())->toBeGreaterThan(0);
        expect($order->subtotal->getMinorAmount())->toBe($order->unit_price->getMinorAmount() * 2);
        expect($order->total->getMinorAmount())->toBe($order->subtotal->getMinorAmount());
    });

    it('uses base price (no member discount)', function () {
        $order = TicketService::guestCheckout(
            $this->event,
            quantity: 1,
            name: 'Guest',
            email: 'guest@example.com',
        );

        $basePrice = $this->event->getBaseTicketPrice()->getMinorAmount();

        expect($order->unit_price->getMinorAmount())->toBe($basePrice);
    });

    it('includes fees when coversFees is true', function () {
        $order = TicketService::guestCheckout(
            $this->event,
            quantity: 1,
            name: 'Guest',
            email: 'guest@example.com',
            coversFees: true,
        );

        expect($order->covers_fees)->toBeTrue();
        expect($order->fees->getMinorAmount())->toBeGreaterThan(0);
        expect($order->total->getMinorAmount())->toBe(
            $order->subtotal->getMinorAmount() + $order->fees->getMinorAmount()
        );
    });

    it('throws when not enough tickets available', function () {
        $this->event->update(['ticket_quantity' => 2]);

        $existingOrder = TicketOrder::factory()->completed()->create([
            'event_id' => $this->event->id,
            'quantity' => 2,
        ]);
        TicketService::generateTickets($existingOrder);

        TicketService::guestCheckout(
            $this->event->fresh(),
            quantity: 1,
            name: 'Guest',
            email: 'guest@example.com',
        );
    })->throws(RuntimeException::class);
});

describe('TicketPurchaseWidget: purchase', function () {
    it('calls checkout with guest info for unauthenticated users', function () {
        TicketService::shouldReceive('checkout')
            ->once()
            ->withArgs(function (Event $event, int $quantity, string $successUrl, string $cancelUrl, ?User $user, ?string $guestName, ?string $guestEmail, bool $coversFees) {
                return $event->id === $this->event->id
                    && $quantity === 1
                    && $user === null
                    && $guestName === 'Jane Doe'
                    && $guestEmail === 'jane@example.com'
                    && $coversFees === false;
            })
            ->andReturn('https://checkout.stripe.com/test');

        Livewire::test(TicketPurchaseWidget::class, ['event' => $this->event])
            ->fillForm([
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'quantity' => 1,
                'covers_fees' => false,
            ])
            ->call('purchase');
    });

    it('calls checkout with user for authenticated users', function () {
        $user = User::factory()->create();

        TicketService::shouldReceive('checkout')
            ->once()
            ->withArgs(function (Event $event, int $quantity, string $successUrl, string $cancelUrl, ?User $passedUser) use ($user) {
                return $event->id === $this->event->id
                    && $quantity === 2
                    && $passedUser?->id === $user->id;
            })
            ->andReturn('https://checkout.stripe.com/test');

        Livewire::actingAs($user)
            ->test(TicketPurchaseWidget::class, ['event' => $this->event])
            ->fillForm([
                'quantity' => 2,
                'covers_fees' => false,
            ])
            ->call('purchase');
    });

    it('displays error message when checkout fails', function () {
        TicketService::shouldReceive('checkout')
            ->once()
            ->andThrow(new RuntimeException('Only 0 tickets available for this event'));

        Livewire::test(TicketPurchaseWidget::class, ['event' => $this->event])
            ->fillForm([
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'quantity' => 1,
            ])
            ->call('purchase')
            ->assertSet('errorMessage', 'Only 0 tickets available for this event')
            ->assertSet('isProcessing', false);
    });
});
