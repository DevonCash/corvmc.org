<?php

use App\Models\User;
use Brick\Money\Money;
use Carbon\Carbon;
use CorvMC\Events\Actions\CreateEvent;
use CorvMC\Events\Actions\Tickets\CompleteTicketOrder;
use CorvMC\Events\Actions\Tickets\CreateTicketOrder;
use CorvMC\Events\Actions\Tickets\GenerateTickets;
use CorvMC\Events\Actions\Tickets\RefundTicketOrder;
use CorvMC\Events\Enums\TicketOrderStatus;
use CorvMC\Events\Enums\TicketStatus;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Models\Ticket;
use CorvMC\Events\Models\TicketOrder;
use CorvMC\Events\Models\Venue;
use CorvMC\Finance\Contracts\Chargeable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Create CMC venue for tests
    $this->cmcVenue = Venue::create([
        'name' => 'Corvallis Music Collective',
        'address' => '420 SW Washington Ave',
        'city' => 'Corvallis',
        'state' => 'OR',
        'zip' => '97333',
        'is_cmc' => true,
    ]);

    // Create an event with ticketing enabled
    $startDatetime = Carbon::now()->addDays(7)->setHour(19)->setMinute(0)->setSecond(0);
    $this->event = CreateEvent::run([
        'title' => 'Rock Concert',
        'start_datetime' => $startDatetime,
        'end_datetime' => $startDatetime->copy()->addHours(3),
        'venue_id' => $this->cmcVenue->id,
        'ticketing_enabled' => true,
        'ticket_quantity' => 100,
    ]);
});

describe('Ticketing: Event Configuration', function () {
    it('can enable native ticketing on an event', function () {
        expect($this->event->ticketing_enabled)->toBeTrue();
        expect($this->event->hasNativeTicketing())->toBeTrue();
    });

    it('returns correct ticket price for guests', function () {
        $price = $this->event->getTicketPriceForUser(null);

        expect($price->getMinorAmount()->toInt())->toBe(config('ticketing.default_price', 1000));
    });

    it('returns discounted price for sustaining members', function () {
        $user = User::factory()->create();
        $user->assignRole('sustaining member');

        $price = $this->event->getTicketPriceForUser($user);
        $basePrice = config('ticketing.default_price', 1000);
        $discountPercent = config('ticketing.sustaining_member_discount', 50);
        $expectedPrice = (int) round($basePrice * (1 - $discountPercent / 100));

        expect($price->getMinorAmount()->toInt())->toBe($expectedPrice);
    });

    it('respects ticket price override', function () {
        $this->event->update(['ticket_price_override' => 1500]);

        $price = $this->event->getBaseTicketPrice();

        expect($price->getMinorAmount()->toInt())->toBe(1500);
    });

    it('tracks tickets remaining correctly', function () {
        expect($this->event->getTicketsRemaining())->toBe(100);

        $this->event->incrementTicketsSold(10);

        expect($this->event->getTicketsRemaining())->toBe(90);
    });

    it('returns null for unlimited tickets', function () {
        $this->event->update(['ticket_quantity' => null]);

        expect($this->event->getTicketsRemaining())->toBeNull();
        expect($this->event->isSoldOut())->toBeFalse();
        expect($this->event->hasTicketsAvailable(1000))->toBeTrue();
    });
});

describe('Ticketing: Create Order', function () {
    it('creates a ticket order for authenticated user', function () {
        $user = User::factory()->create();

        $order = CreateTicketOrder::run(
            event: $this->event,
            quantity: 2,
            user: $user
        );

        expect($order)->toBeInstanceOf(TicketOrder::class);
        expect($order->user_id)->toBe($user->id);
        expect($order->event_id)->toBe($this->event->id);
        expect($order->quantity)->toBe(2);
        expect($order->status)->toBe(TicketOrderStatus::Pending);
    });

    it('creates a ticket order for guest checkout', function () {
        $order = CreateTicketOrder::run(
            event: $this->event,
            quantity: 1,
            name: 'John Doe',
            email: 'john@example.com'
        );

        expect($order)->toBeInstanceOf(TicketOrder::class);
        expect($order->user_id)->toBeNull();
        expect($order->name)->toBe('John Doe');
        expect($order->email)->toBe('john@example.com');
    });

    it('calculates pricing correctly for guest', function () {
        $order = CreateTicketOrder::run(
            event: $this->event,
            quantity: 3,
            email: 'guest@example.com'
        );

        $unitPrice = config('ticketing.default_price', 1000);
        $expectedSubtotal = $unitPrice * 3;

        expect($order->unit_price->getMinorAmount()->toInt())->toBe($unitPrice);
        expect($order->subtotal->getMinorAmount()->toInt())->toBe($expectedSubtotal);
        expect($order->discount->getMinorAmount()->toInt())->toBe(0);
        expect($order->total->getMinorAmount()->toInt())->toBe($expectedSubtotal);
    });

    it('applies sustaining member discount', function () {
        $user = User::factory()->create();
        $user->assignRole('sustaining member');

        $order = CreateTicketOrder::run(
            event: $this->event,
            quantity: 2,
            user: $user
        );

        $basePrice = config('ticketing.default_price', 1000);
        $discountPercent = config('ticketing.sustaining_member_discount', 50);
        $discountedPrice = (int) round($basePrice * (1 - $discountPercent / 100));
        $discountAmount = ($basePrice - $discountedPrice) * 2;

        expect($order->unit_price->getMinorAmount()->toInt())->toBe($discountedPrice);
        expect($order->discount->getMinorAmount()->toInt())->toBe($discountAmount);
    });

    it('throws exception when ticketing not enabled', function () {
        $event = CreateEvent::run([
            'title' => 'No Tickets Event',
            'start_datetime' => Carbon::now()->addDays(7),
            'end_datetime' => Carbon::now()->addDays(7)->addHours(3),
            'venue_id' => $this->cmcVenue->id,
            'ticketing_enabled' => false,
        ]);

        CreateTicketOrder::run(
            event: $event,
            quantity: 1,
            email: 'test@example.com'
        );
    })->throws(\InvalidArgumentException::class);

    it('throws exception when not enough tickets available', function () {
        $this->event->update(['ticket_quantity' => 5, 'tickets_sold' => 4]);

        CreateTicketOrder::run(
            event: $this->event,
            quantity: 2,
            email: 'test@example.com'
        );
    })->throws(\RuntimeException::class);

    it('throws exception when no email or user provided', function () {
        CreateTicketOrder::run(
            event: $this->event,
            quantity: 1
        );
    })->throws(\InvalidArgumentException::class);
});

describe('Ticketing: TicketOrder Implements Chargeable', function () {
    it('implements Chargeable interface', function () {
        $user = User::factory()->create();

        $order = CreateTicketOrder::run(
            event: $this->event,
            quantity: 2,
            user: $user
        );

        expect($order)->toBeInstanceOf(Chargeable::class);
    });

    it('returns correct billable units', function () {
        $user = User::factory()->create();

        $order = CreateTicketOrder::run(
            event: $this->event,
            quantity: 2,
            user: $user
        );

        expect($order->getBillableUnits())->toBe(1.0);
    });

    it('returns correct chargeable description', function () {
        $user = User::factory()->create();

        $order = CreateTicketOrder::run(
            event: $this->event,
            quantity: 2,
            user: $user
        );

        $description = $order->getChargeableDescription();

        expect($description)->toContain('2 ticket(s)');
        expect($description)->toContain($this->event->title);
    });

    it('returns correct billable user', function () {
        $user = User::factory()->create();

        $order = CreateTicketOrder::run(
            event: $this->event,
            quantity: 1,
            user: $user
        );

        expect($order->getBillableUser()->id)->toBe($user->id);
    });
});

describe('Ticketing: Complete Order', function () {
    it('marks order as completed', function () {
        $user = User::factory()->create();

        $order = CreateTicketOrder::run(
            event: $this->event,
            quantity: 2,
            user: $user
        );

        expect($order->status)->toBe(TicketOrderStatus::Pending);

        CompleteTicketOrder::run($order->id, 'cs_test_session_123');

        $order->refresh();

        expect($order->status)->toBe(TicketOrderStatus::Completed);
        expect($order->completed_at)->not->toBeNull();
    });

    it('generates tickets when completing order', function () {
        $user = User::factory()->create();

        $order = CreateTicketOrder::run(
            event: $this->event,
            quantity: 3,
            user: $user
        );

        CompleteTicketOrder::run($order->id, 'cs_test_session_123');

        expect($order->tickets()->count())->toBe(3);
        expect($order->tickets->first()->status)->toBe(TicketStatus::Valid);
    });

    it('increments event tickets sold count', function () {
        $user = User::factory()->create();
        $initialSold = $this->event->tickets_sold;

        $order = CreateTicketOrder::run(
            event: $this->event,
            quantity: 5,
            user: $user
        );

        CompleteTicketOrder::run($order->id, 'cs_test_session_123');

        $this->event->refresh();

        expect($this->event->tickets_sold)->toBe($initialSold + 5);
    });

    it('is idempotent - multiple calls do not duplicate tickets', function () {
        $user = User::factory()->create();

        $order = CreateTicketOrder::run(
            event: $this->event,
            quantity: 2,
            user: $user
        );

        CompleteTicketOrder::run($order->id, 'cs_test_session_123');
        CompleteTicketOrder::run($order->id, 'cs_test_session_123');

        expect($order->tickets()->count())->toBe(2);
    });
});

describe('Ticketing: Generate Tickets', function () {
    it('generates unique ticket codes', function () {
        $order = TicketOrder::factory()->completed()->create([
            'event_id' => $this->event->id,
            'quantity' => 5,
        ]);

        $tickets = GenerateTickets::run($order);

        $codes = $tickets->pluck('code')->toArray();

        expect(count($codes))->toBe(5);
        expect(count(array_unique($codes)))->toBe(5); // All unique
    });

    it('assigns attendee info from order', function () {
        $order = TicketOrder::factory()->completed()->create([
            'event_id' => $this->event->id,
            'quantity' => 1,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $tickets = GenerateTickets::run($order);

        expect($tickets->first()->attendee_name)->toBe('Jane Doe');
        expect($tickets->first()->attendee_email)->toBe('jane@example.com');
    });
});

describe('Ticketing: Ticket Check-In', function () {
    it('can check in a valid ticket', function () {
        $ticket = Ticket::factory()->create([
            'ticket_order_id' => TicketOrder::factory()->completed()->create([
                'event_id' => $this->event->id,
            ])->id,
        ]);

        $staff = User::factory()->create();

        expect($ticket->canCheckIn())->toBeTrue();

        $ticket->checkIn($staff);

        expect($ticket->status)->toBe(TicketStatus::CheckedIn);
        expect($ticket->checked_in_at)->not->toBeNull();
        expect($ticket->checked_in_by)->toBe($staff->id);
    });

    it('cannot check in already checked-in ticket', function () {
        $ticket = Ticket::factory()->checkedIn()->create([
            'ticket_order_id' => TicketOrder::factory()->completed()->create([
                'event_id' => $this->event->id,
            ])->id,
        ]);

        expect($ticket->canCheckIn())->toBeFalse();

        $ticket->checkIn();
    })->throws(\RuntimeException::class);

    it('cannot check in cancelled ticket', function () {
        $ticket = Ticket::factory()->cancelled()->create([
            'ticket_order_id' => TicketOrder::factory()->completed()->create([
                'event_id' => $this->event->id,
            ])->id,
        ]);

        expect($ticket->canCheckIn())->toBeFalse();
    });
});

describe('Ticketing: Refund Order', function () {
    it('refunds a completed order', function () {
        $user = User::factory()->create();

        $order = TicketOrder::factory()->completed()->create([
            'event_id' => $this->event->id,
            'user_id' => $user->id,
            'quantity' => 2,
        ]);

        // Generate tickets first
        GenerateTickets::run($order);
        $this->event->incrementTicketsSold(2);

        $initialSold = $this->event->tickets_sold;

        RefundTicketOrder::run($order, 'Customer requested refund', false);

        $order->refresh();
        $this->event->refresh();

        expect($order->status)->toBe(TicketOrderStatus::Refunded);
        expect($order->refunded_at)->not->toBeNull();
        expect($this->event->tickets_sold)->toBe($initialSold - 2);
    });

    it('cancels all tickets when refunding', function () {
        $order = TicketOrder::factory()->completed()->create([
            'event_id' => $this->event->id,
            'quantity' => 3,
        ]);

        GenerateTickets::run($order);

        RefundTicketOrder::run($order, null, false);

        expect($order->tickets()->where('status', TicketStatus::Cancelled)->count())->toBe(3);
    });

    it('cannot refund pending order', function () {
        $order = TicketOrder::factory()->create([
            'event_id' => $this->event->id,
            'status' => TicketOrderStatus::Pending,
        ]);

        RefundTicketOrder::run($order);
    })->throws(\RuntimeException::class);

    it('cannot refund already refunded order', function () {
        $order = TicketOrder::factory()->refunded()->create([
            'event_id' => $this->event->id,
        ]);

        RefundTicketOrder::run($order);
    })->throws(\RuntimeException::class);
});

describe('Ticketing: Sold Out', function () {
    it('detects sold out event', function () {
        $this->event->update([
            'ticket_quantity' => 10,
            'tickets_sold' => 10,
        ]);

        expect($this->event->isSoldOut())->toBeTrue();
        expect($this->event->hasTicketsAvailable())->toBeFalse();
    });

    it('prevents order creation when sold out', function () {
        $this->event->update([
            'ticket_quantity' => 10,
            'tickets_sold' => 10,
        ]);

        CreateTicketOrder::run(
            event: $this->event,
            quantity: 1,
            email: 'test@example.com'
        );
    })->throws(\RuntimeException::class);
});
