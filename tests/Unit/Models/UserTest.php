<?php

namespace Tests\Unit\Models;

use App\Models\Reservation;
use App\Models\Transaction;
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

    #[Test]
    public function it_identifies_sustaining_member_by_role()
    {
        $this->assertFalse($this->user->isSustainingMember());

        $this->user->assignRole('sustaining member');

        // Clear cache to get fresh result
        Cache::forget("user.{$this->user->id}.is_sustaining");

        $this->assertTrue($this->user->isSustainingMember());
    }

    #[Test]
    public function it_identifies_sustaining_member_by_recurring_transaction()
    {
        $this->assertFalse($this->user->isSustainingMember());

        // Create a recent recurring transaction over $10
        Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'recurring',
            'amount' => 15.00,
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $this->assertTrue($this->user->isSustainingMember());
    }

    #[Test]
    public function it_does_not_identify_sustaining_member_with_old_transaction()
    {
        // Create an old recurring transaction
        Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'recurring',
            'amount' => 15.00,
            'created_at' => Carbon::now()->subMonths(2),
        ]);

        $this->assertFalse($this->user->isSustainingMember());
    }

    #[Test]
    public function it_does_not_identify_sustaining_member_with_small_transaction()
    {
        // Create a recent recurring transaction under $10
        Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'recurring',
            'amount' => 5.00,
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $this->assertFalse($this->user->isSustainingMember());
    }

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

    #[Test]
    public function it_has_transactions_relationship()
    {
        $transaction = Transaction::factory()->create([
            'email' => $this->user->email,
        ]);

        $this->assertTrue($this->user->transactions->contains($transaction));
        $this->assertEquals($this->user->email, $transaction->email);
    }

    #[Test]
    public function it_caches_sustaining_member_status()
    {
        // Clear any existing cache
        Cache::forget("user.{$this->user->id}.is_sustaining");

        $this->assertFalse($this->user->isSustainingMember());

        // Assign role
        $this->user->assignRole('sustaining member');

        // Should still be false due to cache
        $this->assertFalse($this->user->isSustainingMember());

        // Clear cache and check again
        Cache::forget("user.{$this->user->id}.is_sustaining");
        $this->assertTrue($this->user->isSustainingMember());
    }

    #[Test]
    public function it_handles_case_insensitive_email_for_transactions()
    {
        $upperCaseEmail = strtoupper($this->user->email);
        $transaction = Transaction::factory()->create([
            'email' => $upperCaseEmail,
        ]);

        // Should not match due to case sensitivity in database
        $this->assertFalse($this->user->transactions->contains($transaction));
    }

    #[Test]
    public function it_calculates_free_hours_with_partial_hours()
    {
        $this->user->assignRole('sustaining member');

        // Use partial hours
        Reservation::factory()->create([
            'user_id' => $this->user->id,
            'cost' => 0,
            'hours_used' => 1.5,
            'free_hours_used' => 1.5,
            'reserved_at' => Carbon::now()->startOfMonth()->addDays(5),
        ]);

        Reservation::factory()->create([
            'user_id' => $this->user->id,
            'cost' => 0,
            'hours_used' => 0.25,
            'free_hours_used' => 0.25,
            'reserved_at' => Carbon::now()->startOfMonth()->addDays(10),
        ]);

        $this->assertEquals(1.75, $this->user->getUsedFreeHoursThisMonth());
        $this->assertEquals(2.25, $this->user->getRemainingFreeHours());
    }

    #[Test]
    public function it_handles_mixed_free_and_paid_hours_in_same_reservation()
    {
        $this->user->assignRole('sustaining member');

        // Reservation that uses both free and paid hours
        Reservation::factory()->create([
            'user_id' => $this->user->id,
            'cost' => 30.00, // $15/hour for 2 hours
            'hours_used' => 4,
            'free_hours_used' => 2,
            'reserved_at' => Carbon::now()->startOfMonth()->addDays(5),
        ]);

        $this->assertEquals(2, $this->user->getUsedFreeHoursThisMonth());
        $this->assertEquals(2, $this->user->getRemainingFreeHours());
    }

    #[Test]
    public function it_counts_cancelled_reservations_in_free_hours()
    {
        $this->user->assignRole('sustaining member');

        // Create cancelled reservation
        Reservation::factory()->create([
            'user_id' => $this->user->id,
            'cost' => 0,
            'hours_used' => 2,
            'free_hours_used' => 2,
            'status' => 'cancelled',
            'reserved_at' => Carbon::now()->startOfMonth()->addDays(5),
        ]);

        // Cancelled reservations still count towards free hours usage in the current implementation
        $this->assertEquals(2, $this->user->getUsedFreeHoursThisMonth());
        $this->assertEquals(2, $this->user->getRemainingFreeHours());
    }

    #[Test]
    public function it_does_not_identify_sustaining_member_by_one_time_donation()
    {
        // One-time donations don't make someone a sustaining member
        // Only recurring transactions over $10 or the role assignment
        Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'donation', // Not recurring
            'amount' => 120.00,
            'created_at' => Carbon::now()->subDays(5),
        ]);

        $this->assertFalse($this->user->isSustainingMember());
    }

    #[Test]
    public function it_does_not_count_small_recurring_transactions()
    {
        // Recurring transactions must be over $10
        Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'recurring',
            'amount' => 5.00, // Under $10 threshold
            'created_at' => Carbon::now()->subDays(5),
        ]);

        $this->assertFalse($this->user->isSustainingMember());
    }

    #[Test]
    public function it_does_not_count_old_recurring_transactions()
    {
        // Recurring transactions must be within the last month
        Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'recurring',
            'amount' => 15.00,
            'created_at' => Carbon::now()->subMonths(2), // Too old
        ]);

        $this->assertFalse($this->user->isSustainingMember());
    }

    #[Test]
    public function it_creates_user_with_factory()
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->id);
        $this->assertNotNull($user->name);
        $this->assertNotNull($user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse($user->isSustainingMember());
    }

    #[Test]
    public function it_creates_sustaining_member_with_role()
    {
        $user = User::factory()->create();
        $user->assignRole('sustaining member');

        $this->assertTrue($user->hasRole('sustaining member'));
        $this->assertTrue($user->isSustainingMember());
    }
}
