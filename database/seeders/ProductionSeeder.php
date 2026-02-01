<?php

namespace Database\Seeders;

use CorvMC\Bands\Models\Band;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Models\Venue;
use App\Models\EventReservation;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some existing users and bands to work with
        $users = User::take(5)->get();
        $bands = Band::take(10)->get();

        if ($users->isEmpty() || $bands->isEmpty()) {
            $this->command->warn('No users or bands found. Make sure to run UserSeeder and BandSeeder first.');

            return;
        }

        // Get CMC venue for native ticketing
        $cmcVenue = Venue::cmc()->first();

        // Create upcoming events with unique times
        // Enable native ticketing on most CMC events
        $upcomingEvents = collect();
        for ($i = 0; $i < 8; $i++) {
            $factory = Event::factory()->upcoming();

            // Enable native ticketing on 6 of 8 upcoming events (CMC events only)
            if ($i < 6 && $cmcVenue) {
                $ticketsSold = fake()->numberBetween(5, 40);
                $factory = $factory->withNativeTicketing(
                    quantity: fake()->randomElement([75, 100, 150]),
                    sold: $ticketsSold
                );

                $event = $factory->create([
                    'organizer_id' => $users->random()->id,
                    'venue_id' => $cmcVenue->id,
                ]);

                // Create some ticket orders for these events
                $this->createTicketOrders($event, $users, $ticketsSold);
            } else {
                $event = $factory->create([
                    'organizer_id' => $users->random()->id,
                ]);
            }

            $upcomingEvents->push($event);
        }

        // Create completed events with unique times
        // Some with native ticketing history (checked-in tickets)
        $completedEvents = collect();
        for ($i = 0; $i < 12; $i++) {
            $factory = Event::factory()->completed();

            // Enable native ticketing on 4 of 12 completed events
            if ($i < 4 && $cmcVenue) {
                $ticketsSold = fake()->numberBetween(30, 80);
                $factory = $factory->withNativeTicketing(
                    quantity: 100,
                    sold: $ticketsSold
                );

                $event = $factory->create([
                    'organizer_id' => $users->random()->id,
                    'venue_id' => $cmcVenue->id,
                ]);

                // Create ticket orders with checked-in tickets
                $this->createCompletedEventTickets($event, $users, $ticketsSold);
            } else {
                $event = $factory->create([
                    'organizer_id' => $users->random()->id,
                ]);
            }

            $completedEvents->push($event);
        }

        // Create some draft/unpublished events with unique times
        $draftEvents = collect();
        for ($i = 0; $i < 3; $i++) {
            $event = Event::factory()
                ->create([
                    'status' => 'scheduled',
                    'organizer_id' => $users->random()->id,
                    'published_at' => null,
                ]);
            $draftEvents->push($event);
        }

        // Combine all events
        $allEvents = $upcomingEvents
            ->concat($completedEvents)
            ->concat($draftEvents);

        // Attach bands to events with realistic performer counts and set lengths
        foreach ($allEvents as $event) {
            $performerCount = fake()->numberBetween(1, 5);
            $selectedBands = $bands->random($performerCount);

            foreach ($selectedBands as $index => $band) {
                $event->performers()->attach($band->id, [
                    'order' => $index + 1,
                    'set_length' => fake()->numberBetween(20, 60), // 20-60 minute sets
                ]);
            }

            // Add some tags
            $genres = collect(['rock', 'pop', 'jazz', 'folk', 'electronic', 'indie', 'acoustic', 'blues', 'country', 'hip-hop'])
                ->random(fake()->numberBetween(1, 3));

            foreach ($genres as $genre) {
                $event->attachTag($genre, 'genre');
            }

            // Create EventReservation if event is at CMC
            if (! $event->location->is_external) {
                $this->createEventReservation($event);
            }
        }

        // Count events with native ticketing
        $ticketingEventCount = $allEvents->filter(fn ($e) => $e->hasNativeTicketing())->count();
        $ticketOrderCount = \CorvMC\Events\Models\TicketOrder::count();
        $ticketCount = \CorvMC\Events\Models\Ticket::count();

        $this->command->info("Created {$allEvents->count()} events with performers and genres.");
        $this->command->info("  - {$ticketingEventCount} events with native ticketing");
        $this->command->info("  - {$ticketOrderCount} ticket orders, {$ticketCount} tickets");
    }

    /**
     * Create a space reservation for an event at CMC.
     * Includes setup time (1 hour before) and breakdown time (1 hour after).
     */
    private function createEventReservation(Event $event): void
    {
        // Add 1 hour setup before event start and 1 hour breakdown after event end
        $reservedAt = $event->start_datetime->copy()->subHour();
        $reservedUntil = $event->end_datetime->copy()->addHour();

        EventReservation::create([
            'type' => 'event_reservation',
            'reservable_type' => 'event',
            'reservable_id' => $event->id,
            'reserved_at' => $reservedAt,
            'reserved_until' => $reservedUntil,
            'status' => 'confirmed',
            'is_recurring' => false,
            'notes' => 'Space reservation for event: '.$event->title,
        ]);
    }

    /**
     * Create ticket orders and tickets for an event with native ticketing.
     */
    private function createTicketOrders(Event $event, $users, int $ticketsSold): void
    {
        $ticketsCreated = 0;
        $orderCount = fake()->numberBetween(3, min(10, $ticketsSold));

        while ($ticketsCreated < $ticketsSold && $orderCount > 0) {
            $remainingTickets = $ticketsSold - $ticketsCreated;
            $quantity = min(fake()->numberBetween(1, 4), $remainingTickets);

            if ($quantity <= 0) {
                break;
            }

            // 60% member purchases, 40% guest purchases
            $user = fake()->boolean(60) ? $users->random() : null;
            $isSustainingMember = $user?->hasRole('sustaining member') ?? false;

            $unitPrice = $event->ticket_price_override ?? config('ticketing.default_price', 1000);
            if ($isSustainingMember) {
                $discountPercent = config('ticketing.sustaining_member_discount', 50);
                $discountedPrice = (int) round($unitPrice * (1 - $discountPercent / 100));
                $discount = ($unitPrice - $discountedPrice) * $quantity;
                $unitPrice = $discountedPrice;
            } else {
                $discount = 0;
            }

            $subtotal = $unitPrice * $quantity;
            $total = $subtotal;

            $order = \CorvMC\Events\Models\TicketOrder::create([
                'event_id' => $event->id,
                'user_id' => $user?->id,
                'status' => \CorvMC\Events\Enums\TicketOrderStatus::Completed,
                'name' => $user?->name ?? fake()->name(),
                'email' => $user?->email ?? fake()->safeEmail(),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'fees' => 0,
                'total' => $total,
                'covers_fees' => false,
                'is_door_sale' => fake()->boolean(10),
                'payment_method' => fake()->randomElement(['stripe', 'stripe', 'stripe', 'cash', 'card']),
                'completed_at' => fake()->dateTimeBetween('-2 weeks', 'now'),
            ]);

            // Create individual tickets for this order
            for ($i = 0; $i < $quantity; $i++) {
                \CorvMC\Events\Models\Ticket::create([
                    'ticket_order_id' => $order->id,
                    'code' => strtoupper(fake()->bothify('????-####')),
                    'attendee_name' => $user?->name ?? $order->name,
                    'attendee_email' => $user?->email ?? $order->email,
                    'status' => \CorvMC\Events\Enums\TicketStatus::Valid,
                ]);
            }

            $ticketsCreated += $quantity;
            $orderCount--;
        }
    }

    /**
     * Create ticket orders with checked-in tickets for completed events.
     */
    private function createCompletedEventTickets(Event $event, $users, int $ticketsSold): void
    {
        $ticketsCreated = 0;
        $orderCount = fake()->numberBetween(5, min(15, $ticketsSold));
        $staffUser = $users->first(); // Use first user as check-in staff

        while ($ticketsCreated < $ticketsSold && $orderCount > 0) {
            $remainingTickets = $ticketsSold - $ticketsCreated;
            $quantity = min(fake()->numberBetween(1, 4), $remainingTickets);

            if ($quantity <= 0) {
                break;
            }

            $user = fake()->boolean(70) ? $users->random() : null;
            $isSustainingMember = $user?->hasRole('sustaining member') ?? false;

            $unitPrice = $event->ticket_price_override ?? config('ticketing.default_price', 1000);
            if ($isSustainingMember) {
                $discountPercent = config('ticketing.sustaining_member_discount', 50);
                $discountedPrice = (int) round($unitPrice * (1 - $discountPercent / 100));
                $discount = ($unitPrice - $discountedPrice) * $quantity;
                $unitPrice = $discountedPrice;
            } else {
                $discount = 0;
            }

            $subtotal = $unitPrice * $quantity;
            $completedAt = fake()->dateTimeBetween($event->start_datetime->copy()->subWeeks(2), $event->start_datetime);

            $order = \CorvMC\Events\Models\TicketOrder::create([
                'event_id' => $event->id,
                'user_id' => $user?->id,
                'status' => \CorvMC\Events\Enums\TicketOrderStatus::Completed,
                'name' => $user?->name ?? fake()->name(),
                'email' => $user?->email ?? fake()->safeEmail(),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'fees' => 0,
                'total' => $subtotal,
                'covers_fees' => false,
                'is_door_sale' => fake()->boolean(15),
                'payment_method' => fake()->randomElement(['stripe', 'stripe', 'stripe', 'cash', 'card']),
                'completed_at' => $completedAt,
            ]);

            // Create tickets - most checked in for completed events
            for ($i = 0; $i < $quantity; $i++) {
                $isCheckedIn = fake()->boolean(85); // 85% attendance rate

                \CorvMC\Events\Models\Ticket::create([
                    'ticket_order_id' => $order->id,
                    'code' => strtoupper(fake()->bothify('????-####')),
                    'attendee_name' => $user?->name ?? $order->name,
                    'attendee_email' => $user?->email ?? $order->email,
                    'status' => $isCheckedIn
                        ? \CorvMC\Events\Enums\TicketStatus::CheckedIn
                        : \CorvMC\Events\Enums\TicketStatus::Valid,
                    'checked_in_at' => $isCheckedIn
                        ? fake()->dateTimeBetween($event->doors_datetime, $event->start_datetime->copy()->addHour())
                        : null,
                    'checked_in_by' => $isCheckedIn ? $staffUser->id : null,
                ]);
            }

            $ticketsCreated += $quantity;
            $orderCount--;
        }
    }
}
