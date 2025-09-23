<?php

namespace Tests\Unit\Models;

use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    // Note: Sustaining member identification logic is tested in UserSubscriptionServiceTest (Feature tests)
    // This keeps model tests focused on model-specific functionality

    #[Test]
    public function it_calculates_used_free_hours_this_month()
    {
        $this->assertEquals(0, $this->user->getUsedFreeHoursThisMonth());

        // Create reservations with free hours used for this month
        Reservation::factory()->create([
            'user_id' => $this->user->id,
            'cost' => 0,
            'hours_used' => 2,
            'free_hours_used' => 2,
            'reserved_at' => Carbon::now()->startOfMonth()->addDays(5),
        ]);

        Reservation::factory()->create([
            'user_id' => $this->user->id,
            'cost' => 0,
            'hours_used' => 1.5,
            'free_hours_used' => 1.5,
            'reserved_at' => Carbon::now()->startOfMonth()->addDays(10),
        ]);

        // Create a paid reservation (should not count)
        Reservation::factory()->create([
            'user_id' => $this->user->id,
            'cost' => 15.00,
            'hours_used' => 1,
            'free_hours_used' => 0,
            'reserved_at' => Carbon::now()->startOfMonth()->addDays(15),
        ]);

        // Create a free reservation from last month (should not count)
        Reservation::factory()->create([
            'user_id' => $this->user->id,
            'cost' => 0,
            'hours_used' => 2,
            'free_hours_used' => 2,
            'reserved_at' => Carbon::now()->subMonth()->startOfMonth()->addDays(5),
        ]);

        $this->assertEquals(3.5, $this->user->getUsedFreeHoursThisMonth());
    }

    #[Test]
    public function it_calculates_remaining_free_hours_for_regular_user()
    {
        $this->assertEquals(0, $this->user->getRemainingFreeHours());

        // Even with free reservations, regular users don't get free hours
        Reservation::factory()->create([
            'user_id' => $this->user->id,
            'cost' => 0,
            'hours_used' => 2,
            'reserved_at' => Carbon::now()->startOfMonth()->addDays(5),
        ]);

        $this->assertEquals(0, $this->user->getRemainingFreeHours());
    }

    #[Test]
    public function it_calculates_remaining_free_hours_for_sustaining_member()
    {
        $this->user->assignRole('sustaining member');

        // No free hours used yet
        $this->assertEquals(4, $this->user->getRemainingFreeHours());

        // Use 2 free hours
        Reservation::factory()->create([
            'user_id' => $this->user->id,
            'cost' => 0,
            'hours_used' => 2,
            'free_hours_used' => 2,
            'reserved_at' => Carbon::now()->startOfMonth()->addDays(5),
        ]);

        $this->assertEquals(2, $this->user->getRemainingFreeHours());

        // Use all remaining free hours
        Reservation::factory()->create([
            'user_id' => $this->user->id,
            'cost' => 0,
            'hours_used' => 2,
            'free_hours_used' => 2,
            'reserved_at' => Carbon::now()->startOfMonth()->addDays(10),
        ]);

        $this->assertEquals(0, $this->user->getRemainingFreeHours());

        // Use more hours (shouldn't go negative)
        Reservation::factory()->create([
            'user_id' => $this->user->id,
            'cost' => 0,
            'hours_used' => 1,
            'free_hours_used' => 1,
            'reserved_at' => Carbon::now()->startOfMonth()->addDays(15),
        ]);

        $this->assertEquals(0, $this->user->getRemainingFreeHours());
    }

    #[Test]
    public function it_resets_free_hours_each_month()
    {
        $this->user->assignRole('sustaining member');

        // Use all free hours last month
        Reservation::factory()->create([
            'user_id' => $this->user->id,
            'cost' => 0,
            'hours_used' => 4,
            'free_hours_used' => 4,
            'reserved_at' => Carbon::now()->subMonth()->startOfMonth()->addDays(15),
        ]);

        // Should have full 4 hours available this month
        $this->assertEquals(4, $this->user->getRemainingFreeHours());
        $this->assertEquals(0, $this->user->getUsedFreeHoursThisMonth());
    }

    #[Test]
    public function it_has_reservations_relationship()
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->assertTrue($this->user->reservations->contains($reservation));
        $this->assertEquals($this->user->id, $reservation->user_id);
    }

    // Transaction relationship test removed (Transaction model removed)

    #[Test]
    public function it_creates_user_with_factory()
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->id);
        $this->assertNotNull($user->name);
        $this->assertNotNull($user->email);
        $this->assertNotNull($user->email_verified_at);
    }
}
