<?php

use App\Models\Transaction;
use App\Models\User;
use App\Facades\UserSubscriptionService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = User::factory()->create();

    // Role is already created by parent TestCase
    $this->sustainingMemberRole = Role::where('name', 'sustaining member')->first();
});

describe('Sustaining Member Identification', function () {
    it('can identify sustaining member by role', function () {
        $this->user->assignRole('sustaining member');

        expect(UserSubscriptionService::isSustainingMember($this->user))->toBeTrue();
    });

    it('can identify sustaining member by recent transaction', function () {
        Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'recurring',
            'amount' => 15.00,
            'created_at' => now()->subDays(15),
        ]);

        expect(UserSubscriptionService::isSustainingMember($this->user))->toBeTrue();
    });

    it('rejects sustaining member with old transaction', function () {
        Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'recurring',
            'amount' => 15.00,
            'created_at' => now()->subMonths(2),
        ]);

        expect(UserSubscriptionService::isSustainingMember($this->user))->toBeFalse();
    });

    it('rejects sustaining member with low amount', function () {
        Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'recurring',
            'amount' => 5.00,
            'created_at' => now()->subDays(15),
        ]);

        expect(UserSubscriptionService::isSustainingMember($this->user))->toBeFalse();
    });

    it('rejects sustaining member with non recurring transaction', function () {
        Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'one-time',
            'amount' => 15.00,
            'created_at' => now()->subDays(15),
        ]);

        expect(UserSubscriptionService::isSustainingMember($this->user))->toBeFalse();
    });
});

describe('Subscription Status Management', function () {
    it('gets subscription status for sustaining member', function () {
        $this->user->assignRole('sustaining member');

        $transaction = Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'recurring',
            'amount' => 20.00,
            'created_at' => now()->subDays(10),
        ]);

        $status = UserSubscriptionService::getSubscriptionStatus($this->user);

        expect($status['is_sustaining_member'])->toBeTrue()
            ->and($status['free_hours_per_month'])->toBe(4)
            ->and((float) $status['subscription_amount'])->toBe(20.00)
            ->and($status['last_transaction']->id)->toBe($transaction->id)
            ->and($status['next_billing_estimate'])->not->toBeNull();
    });

    it('gets subscription status for regular member', function () {
        $status = UserSubscriptionService::getSubscriptionStatus($this->user);

        expect($status['is_sustaining_member'])->toBeFalse()
            ->and($status['free_hours_per_month'])->toBe(0)
            ->and($status['subscription_amount'])->toBe(0)
            ->and($status['last_transaction'])->toBeNull()
            ->and($status['next_billing_estimate'])->toBeNull();
    });
});

describe('Transaction Processing', function () {
    it('processes qualifying transaction', function () {
        $transaction = Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'recurring',
            'amount' => 15.00,
        ]);

        $result = UserSubscriptionService::processTransaction($transaction);

        expect($result)->toBeTrue();

        $this->user->refresh();
        expect($this->user->hasRole('sustaining member'))->toBeTrue();
    });

    it('does not process non qualifying transaction', function () {
        $transaction = Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'one-time',
            'amount' => 5.00,
        ]);

        $result = UserSubscriptionService::processTransaction($transaction);

        expect($result)->toBeTrue();

        $this->user->refresh();
        expect($this->user->hasRole('sustaining member'))->toBeFalse();
    });

    it('handles transaction without user', function () {
        $transaction = Transaction::factory()->create(['email' => 'nonexistent@example.com']);

        $result = UserSubscriptionService::processTransaction($transaction);

        expect($result)->toBeFalse();
    });
});

describe('Member Queries', function () {
    it('gets sustaining members by role', function () {
        $this->user->assignRole('sustaining member');
        $user2 = User::factory()->create();

        $sustainingMembers = UserSubscriptionService::getSustainingMembers();

        expect($sustainingMembers)->toHaveCount(1)
            ->and($sustainingMembers->first()->id)->toBe($this->user->id);
    });

    it('gets sustaining members by transaction', function () {
        Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'recurring',
            'amount' => 15.00,
            'created_at' => now()->subDays(15),
        ]);

        $sustainingMembers = UserSubscriptionService::getSustainingMembers();

        expect($sustainingMembers)->toHaveCount(1)
            ->and($sustainingMembers->first()->id)->toBe($this->user->id);
    });
});

describe('Subscription Analytics', function () {
    it('gets subscription statistics', function () {
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

        $stats = UserSubscriptionService::getSubscriptionStats();

        expect($stats['total_users'])->toBe(3)
            ->and($stats['sustaining_members'])->toBe(3) // All 3 qualify
            ->and((float) $stats['sustaining_percentage'])->toBe(100.0)
            ->and((float) $stats['monthly_revenue'])->toBe(35.00)
            ->and((float) $stats['average_subscription'])->toBe(17.50)
            ->and($stats['total_free_hours_allocated'])->toBe(12); // 3 * 4 hours
    });

    it('gets expiring subscriptions', function () {
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

        $expiring = UserSubscriptionService::getExpiringSubscriptions(7);

        expect($expiring)->toHaveCount(1)
            ->and($expiring->first()->id)->toBe($this->user->id);
    });
});

describe('Free Hours Management', function () {
    it('calculates free hours usage for month', function () {
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

        $usage = UserSubscriptionService::getFreeHoursUsageForMonth($this->user, now());

        expect($usage['month'])->toBe(now()->format('Y-m'))
            ->and($usage['total_reservations'])->toBe(2)
            ->and($usage['total_hours'])->toBe(5.5)
            ->and($usage['free_hours_used'])->toBe(3.5)
            ->and($usage['paid_hours'])->toBe(2.0)
            ->and($usage['total_cost'])->toBe(30.00)
            ->and($usage['allocated_free_hours'])->toBe(4)
            ->and($usage['unused_free_hours'])->toBe(0.5);
    });
});

describe('Role Management', function () {
    it('revokes sustaining member status', function () {
        $this->user->assignRole('sustaining member');

        $result = UserSubscriptionService::revokeSustainingMemberStatus($this->user);

        expect($result)->toBeTrue();

        $this->user->refresh();
        expect($this->user->hasRole('sustaining member'))->toBeFalse();
    });

    it('does not revoke non sustaining member', function () {
        $result = UserSubscriptionService::revokeSustainingMemberStatus($this->user);

        expect($result)->toBeFalse();
    });

    it('grants sustaining member status', function () {
        $result = UserSubscriptionService::grantSustainingMemberStatus($this->user);

        expect($result)->toBeTrue();

        $this->user->refresh();
        expect($this->user->hasRole('sustaining member'))->toBeTrue();
    });

    it('does not grant existing sustaining member', function () {
        $this->user->assignRole('sustaining member');

        $result = UserSubscriptionService::grantSustainingMemberStatus($this->user);

        expect($result)->toBeFalse();
    });
});
