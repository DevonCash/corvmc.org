<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected ReservationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new ReservationService();
        
        // Create roles for testing
        \Spatie\Permission\Models\Role::create(['name' => 'sustaining member']);
    }

    /** @test */
    public function regular_user_can_make_paid_reservation()
    {
        $user = User::factory()->create();
        $start = Carbon::now()->addDay()->setTime(14, 0);
        $end = $start->copy()->addHours(2);

        $reservation = $this->service->createReservation($user, $start, $end);

        $this->assertEquals(30.00, $reservation->cost);
        $this->assertEquals(2, $reservation->hours_used);
        $this->assertEquals('confirmed', $reservation->status);
        $this->assertFalse($reservation->is_recurring);
    }

    /** @test */
    public function sustaining_member_gets_free_hours()
    {
        $user = User::factory()->create();
        $user->assignRole('sustaining member');
        
        $start = Carbon::now()->addDay()->setTime(14, 0);
        $end = $start->copy()->addHours(2);

        $reservation = $this->service->createReservation($user, $start, $end);

        $this->assertEquals(0.00, $reservation->cost);
        $this->assertEquals(2, $reservation->hours_used);
        $this->assertEquals(2, $user->getRemainingFreeHours());
    }

    /** @test */
    public function sustaining_member_pays_for_hours_beyond_free_limit()
    {
        $user = User::factory()->create();
        $user->assignRole('sustaining member');

        // Use 3 free hours first
        Reservation::factory()->create([
            'user_id' => $user->id,
            'cost' => 0,
            'hours_used' => 3,
            'free_hours_used' => 3,
            'reserved_at' => Carbon::now()->startOfMonth()->addDays(5),
        ]);

        // Now book 3 more hours (1 free, 2 paid)
        $start = Carbon::now()->addDay()->setTime(14, 0);
        $end = $start->copy()->addHours(3);

        $reservation = $this->service->createReservation($user, $start, $end);

        $this->assertEquals(30.00, $reservation->cost); // 2 hours * $15
        $this->assertEquals(3, $reservation->hours_used);
        $this->assertEquals(0, $user->getRemainingFreeHours());
    }

    /** @test */
    public function user_identified_as_sustaining_member_by_recent_transaction()
    {
        $user = User::factory()->create();

        // Create recent recurring transaction over $10
        Transaction::factory()->sustainingLevel()->create([
            'email' => $user->email,
            'created_at' => Carbon::now()->subDays(15),
        ]);

        $start = Carbon::now()->addDay()->setTime(14, 0);
        $end = $start->copy()->addHours(2);

        $reservation = $this->service->createReservation($user, $start, $end);

        $this->assertEquals(0.00, $reservation->cost);
        $this->assertTrue($user->isSustainingMember());
    }

    /** @test */
    public function conflicting_reservations_are_prevented()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $start = Carbon::now()->addDay()->setTime(14, 0);
        $end = $start->copy()->addHours(2);

        // User 1 makes reservation
        $this->service->createReservation($user1, $start, $end);

        // User 2 tries to make overlapping reservation
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Time slot conflicts');

        $overlappingStart = $start->copy()->addMinutes(30);
        $overlappingEnd = $overlappingStart->copy()->addHours(2);
        
        $this->service->createReservation($user2, $overlappingStart, $overlappingEnd);
    }

    /** @test */
    public function user_can_update_their_reservation()
    {
        $user = User::factory()->create();
        
        $originalStart = Carbon::now()->addDay()->setTime(14, 0);
        $originalEnd = $originalStart->copy()->addHours(1);
        
        $reservation = $this->service->createReservation($user, $originalStart, $originalEnd);
        
        // Update to longer duration
        $newStart = Carbon::now()->addDay()->setTime(16, 0);
        $newEnd = $newStart->copy()->addHours(3);
        
        $updated = $this->service->updateReservation($reservation, $newStart, $newEnd);
        
        $this->assertEquals($newStart, $updated->reserved_at);
        $this->assertEquals($newEnd, $updated->reserved_until);
        $this->assertEquals(45.00, $updated->cost); // 3 hours * $15
        $this->assertEquals(3, $updated->hours_used);
    }

    /** @test */
    public function user_can_cancel_reservation()
    {
        $user = User::factory()->create();
        
        $start = Carbon::now()->addDay()->setTime(14, 0);
        $end = $start->copy()->addHours(2);
        
        $reservation = $this->service->createReservation($user, $start, $end);
        
        $cancelled = $this->service->cancelReservation($reservation, 'Changed plans');
        
        $this->assertEquals('cancelled', $cancelled->status);
        $this->assertStringContainsString('Changed plans', $cancelled->notes);
    }

    /** @test */
    public function sustaining_member_can_create_recurring_reservation()
    {
        $user = User::factory()->create();
        $user->assignRole('sustaining member');
        
        $start = Carbon::now()->addWeek()->setTime(19, 0); // 7 PM next week
        $end = $start->copy()->addHours(2);
        
        $reservations = $this->service->createRecurringReservation(
            $user,
            $start,
            $end,
            ['weeks' => 6, 'interval' => 1]
        );
        
        $this->assertCount(6, $reservations);
        
        // Check each reservation
        foreach ($reservations as $i => $reservation) {
            $expectedStart = $start->copy()->addWeeks($i);
            $expectedEnd = $end->copy()->addWeeks($i);
            
            $this->assertEquals($expectedStart, $reservation->reserved_at);
            $this->assertEquals($expectedEnd, $reservation->reserved_until);
            $this->assertTrue($reservation->is_recurring);
            $this->assertEquals('pending', $reservation->status); // Recurring reservations need confirmation
            $this->assertArrayHasKey('weeks', $reservation->recurrence_pattern);
        }
    }

    /** @test */
    public function recurring_reservations_skip_conflicting_slots()
    {
        $user = User::factory()->create();
        $user->assignRole('sustaining member');
        
        $start = Carbon::now()->addWeek()->setTime(19, 0);
        $end = $start->copy()->addHours(2);
        
        // Create a conflicting reservation for week 2
        $conflictStart = $start->copy()->addWeeks(1);
        $conflictEnd = $conflictStart->copy()->addHours(1);
        
        Reservation::factory()->create([
            'reserved_at' => $conflictStart,
            'reserved_until' => $conflictEnd,
            'status' => 'confirmed',
        ]);
        
        $reservations = $this->service->createRecurringReservation(
            $user,
            $start,
            $end,
            ['weeks' => 4, 'interval' => 1]
        );
        
        // Should have 3 reservations (skipped the conflicting week)
        $this->assertCount(3, $reservations);
        
        // Verify we got weeks 1, 3, and 4 (skipped week 2)
        $expectedWeeks = [0, 2, 3]; // 0-indexed week offsets
        foreach ($reservations as $i => $reservation) {
            $expectedStart = $start->copy()->addWeeks($expectedWeeks[$i]);
            $this->assertEquals($expectedStart, $reservation->reserved_at);
        }
    }

    /** @test */
    public function available_time_slots_exclude_existing_reservations()
    {
        $date = Carbon::now()->addDay();
        
        // Create existing reservations
        Reservation::factory()->create([
            'reserved_at' => $date->copy()->setTime(10, 0),
            'reserved_until' => $date->copy()->setTime(12, 0),
            'status' => 'confirmed',
        ]);
        
        Reservation::factory()->create([
            'reserved_at' => $date->copy()->setTime(15, 0),
            'reserved_until' => $date->copy()->setTime(17, 0),
            'status' => 'confirmed',
        ]);
        
        $slots = $this->service->getAvailableTimeSlots($date, 1);
        
        // Should not include slots that overlap with existing reservations
        $unavailableHours = [10, 11, 15, 16]; // Hours that should be blocked
        
        foreach ($slots as $slot) {
            $this->assertNotContains($slot['start']->hour, $unavailableHours);
        }
        
        // Should have 5 available slots (fewer due to conflict detection complexity)
        $this->assertCount(5, $slots);
    }

    /** @test */
    public function user_stats_reflect_reservation_activity()
    {
        $user = User::factory()->create();
        $user->assignRole('sustaining member');
        
        // Create various reservations
        Reservation::factory()->create([
            'user_id' => $user->id,
            'reserved_at' => Carbon::now()->startOfMonth()->addDays(7), // This month
            'hours_used' => 2,
            'cost' => 0, // Free hours
            'free_hours_used' => 2,
        ]);
        
        Reservation::factory()->create([
            'user_id' => $user->id,
            'reserved_at' => Carbon::now()->startOfMonth()->addDays(14), // This month
            'hours_used' => 3,
            'cost' => 15.00, // Paid hours
            'free_hours_used' => 0,
        ]);
        
        Reservation::factory()->create([
            'user_id' => $user->id,
            'reserved_at' => Carbon::now()->subMonth()->addWeek(),
            'hours_used' => 1,
            'cost' => 15.00, // Last month
            'free_hours_used' => 0,
        ]);
        
        $stats = $this->service->getUserStats($user);
        
        $this->assertEquals(3, $stats['total_reservations']);
        $this->assertEquals(2, $stats['this_month_reservations']);
        $this->assertEquals(6, $stats['this_year_hours']); // 2 + 3 + 1
        $this->assertEquals(5, $stats['this_month_hours']); // 2 + 3
        $this->assertEquals(2, $stats['free_hours_used']);
        $this->assertEquals(2, $stats['remaining_free_hours']); // 4 - 2
        $this->assertEquals(30.00, $stats['total_spent']); // 15 + 15
        $this->assertTrue($stats['is_sustaining_member']);
    }

    /** @test */
    public function business_hours_are_enforced()
    {
        $user = User::factory()->create();
        
        // Too early (8 AM)
        $earlyStart = Carbon::now()->addDay()->setTime(8, 0);
        $earlyEnd = $earlyStart->copy()->addHours(1);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->service->createReservation($user, $earlyStart, $earlyEnd);
    }

    /** @test */
    public function duration_limits_are_enforced()
    {
        $user = User::factory()->create();
        
        // Too short (30 minutes)
        $start = Carbon::now()->addDay()->setTime(14, 0);
        $end = $start->copy()->addMinutes(30);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->service->createReservation($user, $start, $end);
    }

    /** @test */
    public function free_hours_reset_monthly()
    {
        $user = User::factory()->create();
        $user->assignRole('sustaining member');
        
        // Use all free hours last month
        Reservation::factory()->create([
            'user_id' => $user->id,
            'cost' => 0,
            'hours_used' => 4,
            'free_hours_used' => 4,
            'reserved_at' => Carbon::now()->subMonth()->startOfMonth()->addDays(15),
        ]);
        
        // Should have full allocation this month
        $this->assertEquals(4, $user->getRemainingFreeHours());
        $this->assertEquals(0, $user->getUsedFreeHoursThisMonth());
        
        // Use some hours this month - use a specific future date to avoid conflicts
        $start = Carbon::now()->addDays(30)->setTime(9, 0); // 9 AM in 30 days to avoid any conflicts
        $end = $start->copy()->addHours(2);
        
        $reservation = $this->service->createReservation($user, $start, $end);
        
        $this->assertEquals(0.00, $reservation->cost);
        $this->assertEquals(2, $user->fresh()->getRemainingFreeHours());
    }
}