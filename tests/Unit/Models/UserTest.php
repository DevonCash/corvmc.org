<?php

namespace Tests\Unit\Models;

use App\Models\Reservation;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles for testing
        \Spatie\Permission\Models\Role::create(['name' => 'sustaining member']);

        $this->user = User::factory()->create();
    }

    #[Test]
    public function it_identifies_sustaining_member_by_role()
    {
        $this->assertFalse($this->user->isSustainingMember());

        $this->user->assignRole('sustaining member');

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
}
