<?php

namespace Database\Seeders;

use CorvMC\Bands\Models\Band;
use CorvMC\Events\Models\Event;
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

        // Create upcoming events with unique times
        $upcomingEvents = collect();
        for ($i = 0; $i < 8; $i++) {
            $event = Event::factory()
                ->upcoming()
                ->create([
                    'organizer_id' => $users->random()->id,
                ]);
            $upcomingEvents->push($event);
        }

        // Create completed events with unique times
        $completedEvents = collect();
        for ($i = 0; $i < 12; $i++) {
            $event = Event::factory()
                ->completed()
                ->create([
                    'organizer_id' => $users->random()->id,
                ]);
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

        $this->command->info('Created '.$allEvents->count().' events with performers and genres.');
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
            'type' => EventReservation::class,
            'reservable_type' => Event::class,
            'reservable_id' => $event->id,
            'reserved_at' => $reservedAt,
            'reserved_until' => $reservedUntil,
            'status' => 'confirmed',
            'payment_status' => 'n/a',
            'cost' => 0, // Events don't pay for space
            'hours_used' => $reservedAt->diffInMinutes($reservedUntil) / 60,
            'free_hours_used' => 0,
            'is_recurring' => false,
            'notes' => 'Space reservation for event: '.$event->title,
        ]);
    }
}
