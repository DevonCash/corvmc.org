<?php

namespace Tests\Unit\Services;

use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReservationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ReservationService $service;

    protected User $regularUser;

    protected User $sustainingUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles for testing
        \Spatie\Permission\Models\Role::create(['name' => 'sustaining member']);

        $this->service = new ReservationService;

        // Create test users
        $this->regularUser = User::factory()->create();
        $this->sustainingUser = User::factory()->create();
        $this->sustainingUser->assignRole('sustaining member');
    }

    #[Test]
    public function it_calculates_cost_correctly_for_regular_user()
    {
        $start = Carbon::now()->addDay()->setTime(14, 0);
        $end = $start->copy()->addHours(2);

        $result = $this->service->calculateCost($this->regularUser, $start, $end);

        $this->assertEquals(2, $result['total_hours']);
        $this->assertEquals(0, $result['free_hours']);
        $this->assertEquals(2, $result['paid_hours']);
        $this->assertEquals(30.00, $result['cost']);
        $this->assertEquals(15.00, $result['hourly_rate']);
        $this->assertFalse($result['is_sustaining_member']);
        $this->assertEquals(0, $result['remaining_free_hours']);
    }

    #[Test]
    public function it_calculates_cost_correctly_for_sustaining_member()
    {
        $start = Carbon::now()->addDay()->setTime(14, 0);
        $end = $start->copy()->addHours(2);

        $result = $this->service->calculateCost($this->sustainingUser, $start, $end);

        $this->assertEquals(2, $result['total_hours']);
        $this->assertEquals(2, $result['free_hours']);
        $this->assertEquals(0, $result['paid_hours']);
        $this->assertEquals(0.00, $result['cost']);
        $this->assertTrue($result['is_sustaining_member']);
        $this->assertEquals(4, $result['remaining_free_hours']);
    }

    #[Test]
    public function it_calculates_partial_free_hours_for_sustaining_member()
    {
        // Create existing reservation that uses 2 free hours this month
        Reservation::factory()->create([
            'user_id' => $this->sustainingUser->id,
            'cost' => 0,
            'hours_used' => 2,
            'free_hours_used' => 2,
            'reserved_at' => Carbon::now()->startOfMonth()->addDays(5),
            'reserved_until' => Carbon::now()->startOfMonth()->addDays(5)->addHours(2),
        ]);

        $start = Carbon::now()->addDay()->setTime(14, 0);
        $end = $start->copy()->addHours(3); // 3 hours requested

        $result = $this->service->calculateCost($this->sustainingUser, $start, $end);

        $this->assertEquals(3, $result['total_hours']);
        $this->assertEquals(2, $result['free_hours']); // Only 2 free hours remaining
        $this->assertEquals(1, $result['paid_hours']); // 1 hour paid
        $this->assertEquals(15.00, $result['cost']);
        $this->assertEquals(2, $result['remaining_free_hours']);
    }

    #[Test]
    public function it_calculates_hours_correctly()
    {
        $start = Carbon::parse('2025-01-01 14:00:00');
        $end = Carbon::parse('2025-01-01 16:30:00');

        $hours = $this->service->calculateHours($start, $end);

        $this->assertEquals(2.5, $hours);
    }

    #[Test]
    public function it_detects_time_slot_availability()
    {
        $start = Carbon::now()->addDay()->setTime(14, 0);
        $end = $start->copy()->addHours(2);

        // No existing reservations - should be available
        $this->assertTrue($this->service->isTimeSlotAvailable($start, $end));

        // Create overlapping reservation
        Reservation::factory()->create([
            'reserved_at' => $start->copy()->addMinutes(30),
            'reserved_until' => $start->copy()->addHours(1),
            'status' => 'confirmed',
        ]);

        // Should now be unavailable
        $this->assertFalse($this->service->isTimeSlotAvailable($start, $end));
    }

    #[Test]
    public function it_excludes_reservation_from_conflict_check()
    {
        $start = Carbon::now()->addDay()->setTime(14, 0);
        $end = $start->copy()->addHours(2);

        $reservation = Reservation::factory()->create([
            'reserved_at' => $start,
            'reserved_until' => $end,
            'status' => 'confirmed',
        ]);

        // Should be available when excluding the existing reservation
        $this->assertTrue($this->service->isTimeSlotAvailable($start, $end, $reservation->id));
    }

    #[Test]
    public function it_ignores_cancelled_reservations_for_availability()
    {
        $start = Carbon::now()->addDay()->setTime(14, 0);
        $end = $start->copy()->addHours(2);

        Reservation::factory()->create([
            'reserved_at' => $start,
            'reserved_until' => $end,
            'status' => 'cancelled',
        ]);

        // Should be available since cancelled reservations are ignored
        $this->assertTrue($this->service->isTimeSlotAvailable($start, $end));
    }

    #[Test]
    public function it_finds_conflicting_reservations()
    {
        $start = Carbon::now()->addDay()->setTime(14, 0);
        $end = $start->copy()->addHours(2);

        $conflict1 = Reservation::factory()->create([
            'reserved_at' => $start->copy()->addMinutes(30),
            'reserved_until' => $start->copy()->addHours(1),
            'status' => 'confirmed',
        ]);

        $conflict2 = Reservation::factory()->create([
            'reserved_at' => $start->copy()->addHours(1),
            'reserved_until' => $end,
            'status' => 'pending',
        ]);

        $conflicts = $this->service->getConflictingReservations($start, $end);

        $this->assertCount(2, $conflicts);
        $this->assertTrue($conflicts->contains('id', $conflict1->id));
        $this->assertTrue($conflicts->contains('id', $conflict2->id));
    }

    #[Test]
    public function it_validates_reservation_start_time_in_future()
    {
        $pastStart = Carbon::now()->subHour();
        $pastEnd = $pastStart->copy()->addHours(2);

        $errors = $this->service->validateReservation($this->regularUser, $pastStart, $pastEnd);

        $this->assertContains('Reservation start time must be in the future.', $errors);
    }

    #[Test]
    public function it_validates_end_time_after_start_time()
    {
        $start = Carbon::now()->addDay()->setTime(14, 0);
        $end = $start->copy()->subHour(); // End before start

        $errors = $this->service->validateReservation($this->regularUser, $start, $end);

        $this->assertContains('End time must be after start time.', $errors);
    }

    #[Test]
    public function it_validates_minimum_duration()
    {
        $start = Carbon::now()->addDay()->setTime(14, 0);
        $end = $start->copy()->addMinutes(30); // 30 minutes

        $errors = $this->service->validateReservation($this->regularUser, $start, $end);

        $this->assertContains('Minimum reservation duration is 1 hour(s).', $errors);
    }

    #[Test]
    public function it_validates_maximum_duration()
    {
        $start = Carbon::now()->addDay()->setTime(14, 0);
        $end = $start->copy()->addHours(10); // 10 hours

        $errors = $this->service->validateReservation($this->regularUser, $start, $end);

        $this->assertContains('Maximum reservation duration is 8 hours.', $errors);
    }

    #[Test]
    public function it_validates_business_hours()
    {
        // Too early
        $earlyStart = Carbon::now()->addDay()->setTime(8, 0);
        $earlyEnd = $earlyStart->copy()->addHours(2);

        $errors = $this->service->validateReservation($this->regularUser, $earlyStart, $earlyEnd);
        $this->assertContains('Reservations are only allowed between 9 AM and 10 PM.', $errors);

        // Too late
        $lateStart = Carbon::now()->addDay()->setTime(21, 0);
        $lateEnd = $lateStart->copy()->addHours(2); // Ends at 11 PM

        $errors = $this->service->validateReservation($this->regularUser, $lateStart, $lateEnd);
        $this->assertContains('Reservations are only allowed between 9 AM and 10 PM.', $errors);
    }

    #[Test]
    public function it_creates_reservation_successfully()
    {
        $start = Carbon::now()->addDay()->setTime(14, 0);
        $end = $start->copy()->addHours(2);

        $reservation = $this->service->createReservation($this->regularUser, $start, $end);

        $this->assertInstanceOf(Reservation::class, $reservation);
        $this->assertEquals($this->regularUser->id, $reservation->user_id);
        $this->assertEquals($start, $reservation->reserved_at);
        $this->assertEquals($end, $reservation->reserved_until);
        $this->assertEquals(30.00, $reservation->cost);
        $this->assertEquals(2, $reservation->hours_used);
        $this->assertEquals('confirmed', $reservation->status);
    }

    #[Test]
    public function it_creates_reservation_with_options()
    {
        $start = Carbon::now()->addDay()->setTime(14, 0);
        $end = $start->copy()->addHours(2);

        $reservation = $this->service->createReservation($this->regularUser, $start, $end, [
            'status' => 'pending',
            'notes' => 'Test reservation',
            'is_recurring' => true,
        ]);

        $this->assertEquals('pending', $reservation->status);
        $this->assertEquals('Test reservation', $reservation->notes);
        $this->assertTrue($reservation->is_recurring);
    }

    #[Test]
    public function it_throws_exception_for_invalid_reservation()
    {
        $start = Carbon::now()->subHour(); // Past time
        $end = $start->copy()->addHours(2);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Validation failed:');

        $this->service->createReservation($this->regularUser, $start, $end);
    }

    #[Test]
    public function it_updates_reservation_successfully()
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->regularUser->id,
            'cost' => 15.00,
            'hours_used' => 1,
        ]);

        $newStart = Carbon::now()->addDay()->setTime(16, 0);
        $newEnd = $newStart->copy()->addHours(3);

        $updated = $this->service->updateReservation($reservation, $newStart, $newEnd);

        $this->assertEquals($newStart, $updated->reserved_at);
        $this->assertEquals($newEnd, $updated->reserved_until);
        $this->assertEquals(45.00, $updated->cost); // 3 hours * $15
        $this->assertEquals(3, $updated->hours_used);
    }

    #[Test]
    public function it_cancels_reservation_successfully()
    {
        $reservation = Reservation::factory()->create([
            'status' => 'confirmed',
            'notes' => 'Original notes',
        ]);

        $cancelled = $this->service->cancelReservation($reservation, 'User requested cancellation');

        $this->assertEquals('cancelled', $cancelled->status);
        $this->assertStringContainsString('Cancellation reason: User requested cancellation', $cancelled->notes);
    }

    #[Test]
    public function it_gets_available_time_slots()
    {
        $date = Carbon::now()->addDay();

        // Create one existing reservation to block some slots
        Reservation::factory()->create([
            'reserved_at' => $date->copy()->setTime(14, 0),
            'reserved_until' => $date->copy()->setTime(16, 0),
            'status' => 'confirmed',
        ]);

        $slots = $this->service->getAvailableTimeSlots($date, 1);

        // Should have 9 available slots (13 total hours - 2 blocked hours = 11, but slot boundaries affect this)
        $this->assertCount(9, $slots);
    }

    #[Test]
    public function it_creates_recurring_reservations_for_sustaining_member()
    {
        $start = Carbon::now()->addWeek()->setTime(14, 0);
        $end = $start->copy()->addHours(2);

        $reservations = $this->service->createRecurringReservation(
            $this->sustainingUser,
            $start,
            $end,
            ['weeks' => 4, 'interval' => 1]
        );

        $this->assertCount(4, $reservations);

        foreach ($reservations as $i => $reservation) {
            $expectedStart = $start->copy()->addWeeks($i);
            $this->assertEquals($expectedStart, $reservation->reserved_at);
            $this->assertTrue($reservation->is_recurring);
            $this->assertEquals('pending', $reservation->status);
        }
    }

    #[Test]
    public function it_throws_exception_for_non_sustaining_member_recurring_reservation()
    {
        $start = Carbon::now()->addWeek()->setTime(14, 0);
        $end = $start->copy()->addHours(2);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only sustaining members can create recurring reservations.');

        $this->service->createRecurringReservation(
            $this->regularUser,
            $start,
            $end,
            ['weeks' => 4]
        );
    }

    #[Test]
    public function it_gets_user_stats_correctly()
    {
        // Create some reservations for the user
        Reservation::factory()->create([
            'user_id' => $this->sustainingUser->id,
            'reserved_at' => Carbon::now()->startOfMonth()->addDays(7), // This month
            'hours_used' => 2,
            'cost' => 0, // Free hours
            'free_hours_used' => 2,
        ]);

        Reservation::factory()->create([
            'user_id' => $this->sustainingUser->id,
            'reserved_at' => Carbon::now()->subMonth()->addWeek(), // Last month
            'hours_used' => 3,
            'cost' => 45.00,
            'free_hours_used' => 0,
        ]);

        $stats = $this->service->getUserStats($this->sustainingUser);

        $this->assertEquals(2, $stats['total_reservations']);
        $this->assertEquals(1, $stats['this_month_reservations']);
        $this->assertEquals(5, $stats['this_year_hours']); // 2 + 3
        $this->assertEquals(2, $stats['this_month_hours']); // Only the first reservation is this month
        $this->assertEquals(2, $stats['free_hours_used']);
        $this->assertEquals(2, $stats['remaining_free_hours']); // 4 - 2
        $this->assertEquals(45.00, $stats['total_spent']);
        $this->assertTrue($stats['is_sustaining_member']);
    }
}
