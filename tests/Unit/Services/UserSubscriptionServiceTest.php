<?php

namespace Tests\Unit\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Services\UserSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserSubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected UserSubscriptionService $service;

    protected User $user;

    protected Role $sustainingMemberRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new UserSubscriptionService;
        $this->user = User::factory()->create();

        // Create sustaining member role
        $this->sustainingMemberRole = Role::create(['name' => 'sustaining member']);
    }

    #[Test]
    public function it_identifies_sustaining_member_by_role()
    {
        $this->user->assignRole('sustaining member');

        $this->assertTrue($this->service->isSustainingMember($this->user));
    }

    #[Test]
    public function it_identifies_sustaining_member_by_recent_transaction()
    {
        Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'recurring',
            'amount' => 15.00,
            'created_at' => now()->subDays(15),
        ]);

        $this->assertTrue($this->service->isSustainingMember($this->user));
    }

    #[Test]
    public function it_rejects_sustaining_member_with_old_transaction()
    {
        Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'recurring',
            'amount' => 15.00,
            'created_at' => now()->subMonths(2),
        ]);

        $this->assertFalse($this->service->isSustainingMember($this->user));
    }

    #[Test]
    public function it_rejects_sustaining_member_with_low_amount()
    {
        Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'recurring',
            'amount' => 5.00,
            'created_at' => now()->subDays(15),
        ]);

        $this->assertFalse($this->service->isSustainingMember($this->user));
    }

    #[Test]
    public function it_rejects_sustaining_member_with_non_recurring_transaction()
    {
        Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'one-time',
            'amount' => 15.00,
            'created_at' => now()->subDays(15),
        ]);

        $this->assertFalse($this->service->isSustainingMember($this->user));
    }

    #[Test]
    public function it_gets_subscription_status_for_sustaining_member()
    {
        $this->user->assignRole('sustaining member');

        $transaction = Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'recurring',
            'amount' => 20.00,
            'created_at' => now()->subDays(10),
        ]);

        $status = $this->service->getSubscriptionStatus($this->user);

        $this->assertTrue($status['is_sustaining_member']);
        $this->assertEquals(4, $status['free_hours_per_month']);
        $this->assertEquals(20.00, $status['subscription_amount']);
        $this->assertEquals($transaction->id, $status['last_transaction']->id);
        $this->assertNotNull($status['next_billing_estimate']);
    }

    #[Test]
    public function it_gets_subscription_status_for_regular_member()
    {
        $status = $this->service->getSubscriptionStatus($this->user);

        $this->assertFalse($status['is_sustaining_member']);
        $this->assertEquals(0, $status['free_hours_per_month']);
        $this->assertEquals(0, $status['subscription_amount']);
        $this->assertNull($status['last_transaction']);
        $this->assertNull($status['next_billing_estimate']);
    }

    #[Test]
    public function it_processes_qualifying_transaction()
    {
        $transaction = Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'recurring',
            'amount' => 15.00,
        ]);

        $result = $this->service->processTransaction($transaction);

        $this->assertTrue($result);
        $this->user->refresh();
        $this->assertTrue($this->user->hasRole('sustaining member'));
    }

    #[Test]
    public function it_does_not_process_non_qualifying_transaction()
    {
        $transaction = Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'one-time',
            'amount' => 5.00,
        ]);

        $result = $this->service->processTransaction($transaction);

        $this->assertTrue($result);
        $this->user->refresh();
        $this->assertFalse($this->user->hasRole('sustaining member'));
    }

    #[Test]
    public function it_handles_transaction_without_user()
    {
        $transaction = Transaction::factory()->create(['email' => 'nonexistent@example.com']);

        $result = $this->service->processTransaction($transaction);

        $this->assertFalse($result);
    }

    #[Test]
    public function it_gets_sustaining_members_by_role()
    {
        $this->user->assignRole('sustaining member');
        $user2 = User::factory()->create();

        $sustainingMembers = $this->service->getSustainingMembers();

        $this->assertCount(1, $sustainingMembers);
        $this->assertEquals($this->user->id, $sustainingMembers->first()->id);
    }

    #[Test]
    public function it_gets_sustaining_members_by_transaction()
    {
        Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'recurring',
            'amount' => 15.00,
            'created_at' => now()->subDays(15),
        ]);

        $sustainingMembers = $this->service->getSustainingMembers();

        $this->assertCount(1, $sustainingMembers);
        $this->assertEquals($this->user->id, $sustainingMembers->first()->id);
    }

    #[Test]
    public function it_gets_subscription_statistics()
    {
        // Create sustaining member by role only
        $this->user->assignRole('sustaining member');

        // Create second user with transaction (will be considered sustaining)
        $user2 = User::factory()->create();
        Transaction::factory()->create([
            'email' => $user2->email,
            'type' => 'recurring',
            'amount' => 15.00,
            'created_at' => now()->subDays(5),
        ]);

        // Create third user with transaction (will also be considered sustaining)
        $user3 = User::factory()->create();
        Transaction::factory()->create([
            'email' => $user3->email,
            'type' => 'recurring',
            'amount' => 20.00,
            'created_at' => now()->subDays(10),
        ]);

        $stats = $this->service->getSubscriptionStats();

        $this->assertEquals(3, $stats['total_users']);
        $this->assertEquals(3, $stats['sustaining_members']); // All 3 qualify
        $this->assertEquals(100.0, $stats['sustaining_percentage']);
        $this->assertEquals(35.00, $stats['monthly_revenue']);
        $this->assertEquals(17.50, $stats['average_subscription']);
        $this->assertEquals(12, $stats['total_free_hours_allocated']); // 3 * 4 hours
    }

    #[Test]
    public function it_gets_expiring_subscriptions()
    {
        // Create user with old transaction (should be expiring)
        Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'recurring',
            'amount' => 15.00,
            'created_at' => now()->subMonth()->subDays(5), // 35 days ago
        ]);

        // Create user with recent transaction (should not be expiring)
        $user2 = User::factory()->create();
        Transaction::factory()->create([
            'email' => $user2->email,
            'type' => 'recurring',
            'amount' => 15.00,
            'created_at' => now()->subDays(10),
        ]);

        $expiring = $this->service->getExpiringSubscriptions(7);

        $this->assertCount(1, $expiring);
        $this->assertEquals($this->user->id, $expiring->first()->id);
    }

    #[Test]
    public function it_calculates_free_hours_usage_for_month()
    {
        $this->user->assignRole('sustaining member');

        // Create reservations for the current month
        $this->user->reservations()->create([
            'reserved_at' => now(),
            'hours_used' => 3.5,
            'free_hours_used' => 2.0,
            'cost' => 22.50, // (3.5 - 2.0) * 15
        ]);

        $this->user->reservations()->create([
            'reserved_at' => now()->addDays(5),
            'hours_used' => 2.0,
            'free_hours_used' => 1.5,
            'cost' => 7.50, // (2.0 - 1.5) * 15
        ]);

        $usage = $this->service->getFreeHoursUsageForMonth($this->user, now());

        $this->assertEquals(now()->format('Y-m'), $usage['month']);
        $this->assertEquals(2, $usage['total_reservations']);
        $this->assertEquals(5.5, $usage['total_hours']);
        $this->assertEquals(3.5, $usage['free_hours_used']);
        $this->assertEquals(2.0, $usage['paid_hours']);
        $this->assertEquals(30.00, $usage['total_cost']);
        $this->assertEquals(4, $usage['allocated_free_hours']);
        $this->assertEquals(0.5, $usage['unused_free_hours']);
    }

    #[Test]
    public function it_revokes_sustaining_member_status()
    {
        $this->user->assignRole('sustaining member');

        $result = $this->service->revokeSustainingMemberStatus($this->user);

        $this->assertTrue($result);
        $this->user->refresh();
        $this->assertFalse($this->user->hasRole('sustaining member'));
    }

    #[Test]
    public function it_does_not_revoke_non_sustaining_member()
    {
        $result = $this->service->revokeSustainingMemberStatus($this->user);

        $this->assertFalse($result);
    }

    #[Test]
    public function it_grants_sustaining_member_status()
    {
        $result = $this->service->grantSustainingMemberStatus($this->user);

        $this->assertTrue($result);
        $this->user->refresh();
        $this->assertTrue($this->user->hasRole('sustaining member'));
    }

    #[Test]
    public function it_does_not_grant_existing_sustaining_member()
    {
        $this->user->assignRole('sustaining member');

        $result = $this->service->grantSustainingMemberStatus($this->user);

        $this->assertFalse($result);
    }
}
