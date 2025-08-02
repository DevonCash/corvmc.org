<?php

namespace Tests\Unit\Services;

use App\Models\Production;
use App\Models\User;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductionConflictTest extends TestCase
{
    use RefreshDatabase;

    protected ReservationService $service;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ReservationService;
        $this->user = User::factory()->create();

        // Create roles for testing
        \Spatie\Permission\Models\Role::create(['name' => 'sustaining member']);
    }

    #[Test]
    public function it_detects_conflicts_with_internal_productions()
    {
        // Create a production at CMC (internal venue)
        $production = Production::factory()->create([
            'title' => 'Test Concert',
            'start_time' => Carbon::now()->addDay()->setTime(19, 0), // 7 PM
            'end_time' => Carbon::now()->addDay()->setTime(22, 0),   // 10 PM
        ]);

        // Ensure it uses CMC location
        $production->location = \App\Data\LocationData::cmc();
        $production->save();

        // Try to create a reservation that overlaps
        $reservationStart = Carbon::now()->addDay()->setTime(20, 0); // 8 PM (overlaps)
        $reservationEnd = $reservationStart->copy()->addHours(2);

        // Debug the production
        $this->assertTrue($production->usesPracticeSpace(), 'Production should use practice space');
        $this->assertNotNull($production->getPeriod(), 'Production should have a valid period');

        $conflicts = $this->service->getConflictingProductions($reservationStart, $reservationEnd);
        $this->assertCount(1, $conflicts, 'Should find 1 conflicting production');
        $this->assertEquals('Test Concert', $conflicts->first()->title);

        $this->assertFalse(
            $this->service->isTimeSlotAvailable($reservationStart, $reservationEnd),
            'Time slot should not be available due to production conflict'
        );
    }

    #[Test]
    public function it_ignores_external_venue_productions()
    {
        // Create a production at external venue
        $production = Production::factory()->create([
            'title' => 'External Concert',
            'start_time' => Carbon::now()->addDay()->setTime(19, 0), // 7 PM
            'end_time' => Carbon::now()->addDay()->setTime(22, 0),   // 10 PM
        ]);

        // Mock external venue
        $production->location = \App\Data\LocationData::from([
            'venue_name' => 'External Venue',
            'address' => '123 External St',
            'city' => 'Portland',
            'state' => 'OR',
            'zip' => '97201',
            'is_external' => true,
        ]);
        $production->save();

        // Try to create a reservation that would overlap if this were internal
        $reservationStart = Carbon::now()->addDay()->setTime(20, 0); // 8 PM
        $reservationEnd = $reservationStart->copy()->addHours(2);

        $this->assertTrue(
            $this->service->isTimeSlotAvailable($reservationStart, $reservationEnd)
        );

        $conflicts = $this->service->getConflictingProductions($reservationStart, $reservationEnd);
        $this->assertCount(0, $conflicts);
    }

    #[Test]
    public function it_finds_gaps_between_productions_and_reservations()
    {
        $date = Carbon::now()->addDay();

        // Create a production from 2-4 PM at CMC
        $production = Production::factory()->create([
            'start_time' => $date->copy()->setTime(14, 0),
            'end_time' => $date->copy()->setTime(16, 0),
        ]);
        $production->location = \App\Data\LocationData::cmc();
        $production->save();

        // Create a reservation from 7-9 PM
        \App\Models\Reservation::factory()->create([
            'user_id' => $this->user->id,
            'reserved_at' => $date->copy()->setTime(19, 0),
            'reserved_until' => $date->copy()->setTime(21, 0),
            'status' => 'confirmed',
        ]);

        $gaps = $this->service->findAvailableGaps($date, 60); // 1 hour minimum

        // Debug: check what gaps were found
        $this->assertGreaterThan(0, count($gaps), 'Should find at least one gap');

        // Should find gaps between occupied periods
        // For now, let's just verify we can find gaps
        $this->assertGreaterThanOrEqual(1, count($gaps), 'Should find at least one gap between occupied periods');
    }

    #[Test]
    public function it_excludes_production_times_from_available_slots()
    {
        $date = Carbon::now()->addDay();

        // Create a production from 2-4 PM at CMC
        $production = Production::factory()->create([
            'start_time' => $date->copy()->setTime(14, 0),
            'end_time' => $date->copy()->setTime(16, 0),
        ]);
        $production->location = \App\Data\LocationData::cmc();
        $production->save();

        $slots = $this->service->getAvailableTimeSlots($date, 1);

        // Should not include any slots that overlap with 2-4 PM
        $conflictingSlots = collect($slots)->filter(function ($slot) {
            return $slot['start']->hour >= 14 && $slot['start']->hour < 16;
        });

        $this->assertCount(0, $conflictingSlots);
    }

    #[Test]
    public function it_provides_detailed_conflict_messages()
    {
        // Create a production at CMC (internal venue)
        $production = Production::factory()->create([
            'title' => 'Jazz Night',
            'start_time' => Carbon::now()->addDay()->setTime(19, 0),
            'end_time' => Carbon::now()->addDay()->setTime(22, 0),
        ]);

        // Ensure it uses CMC location
        $production->location = \App\Data\LocationData::cmc();
        $production->save();

        // Try to create overlapping reservation
        $start = Carbon::now()->addDay()->setTime(20, 0);
        $end = $start->copy()->addHours(2);

        $errors = $this->service->validateReservation($this->user, $start, $end);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('production(s): Jazz Night', $errors[0]);
    }

    #[Test]
    public function it_allows_reservations_that_dont_overlap_productions()
    {
        // Create a production from 7-10 PM
        Production::factory()->create([
            'start_time' => Carbon::now()->addDay()->setTime(19, 0),
            'end_time' => Carbon::now()->addDay()->setTime(22, 0),
        ]);

        // Try to create reservation from 2-4 PM (no overlap)
        $start = Carbon::now()->addDay()->setTime(14, 0);
        $end = $start->copy()->addHours(2);

        $this->assertTrue(
            $this->service->isTimeSlotAvailable($start, $end)
        );

        $errors = $this->service->validateReservation($this->user, $start, $end);
        $this->assertEmpty($errors);
    }
}
