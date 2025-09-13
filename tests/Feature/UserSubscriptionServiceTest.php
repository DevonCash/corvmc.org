<?php

use App\Facades\UserSubscriptionService;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('UserSubscriptionService', function () {
    it('identifies sustaining members by role', function () {
        $this->user->assignRole('sustaining member');

        expect(UserSubscriptionService::isSustainingMember($this->user))->toBeTrue();
    });

    it('identifies sustaining members by recurring donations', function () {
        Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'recurring',
            'amount' => 25.00,
            'created_at' => now()->subDays(15), // Within last month
        ]);

        expect(UserSubscriptionService::isSustainingMember($this->user))->toBeTrue();
    });

    it('does not identify non-sustaining members', function () {
        // User with no role and no qualifying transactions
        expect(UserSubscriptionService::isSustainingMember($this->user))->toBeFalse();

        // User with small donation
        Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'recurring',
            'amount' => 5.00,
            'created_at' => now()->subDays(15),
        ]);

        expect(UserSubscriptionService::isSustainingMember($this->user))->toBeFalse();
    });

    it('calculates free hours correctly', function () {
        expect(UserSubscriptionService::calculateFreeHours(25.00))->toBe(5); // 25/5 = 5 hours
        expect(UserSubscriptionService::calculateFreeHours(10.00))->toBe(2); // 10/5 = 2 hours
        expect(UserSubscriptionService::calculateFreeHours(7.50))->toBe(1);  // 7.5/5 = 1.5, floored to 1
        expect(UserSubscriptionService::calculateFreeHours(3.00))->toBe(0);  // 3/5 = 0.6, floored to 0
    });

    it('returns correct monthly free hours for sustaining members', function () {
        $this->user->assignRole('sustaining member');

        expect(UserSubscriptionService::getUserMonthlyFreeHours($this->user))->toBe(4);
    });

    it('returns zero free hours for non-sustaining members', function () {
        expect(UserSubscriptionService::getUserMonthlyFreeHours($this->user))->toBe(0);
    });

    it('calculates remaining free hours correctly', function () {
        $this->user->assignRole('sustaining member');

        // Create some reservations with used free hours to test the calculation
        \App\Models\Reservation::factory()->create([
            'user_id' => $this->user->id,
            'reserved_at' => now(),
            'free_hours_used' => 2.5,
            'hours_used' => 3,
            'cost' => 7.50, // $15/hr for 0.5 paid hours
        ]);

        $remainingHours = UserSubscriptionService::getRemainingFreeHours($this->user);
        expect($remainingHours)->toBe(1.5); // 4 - 2.5 = 1.5
    });

    it('processes transactions and upgrades users', function () {
        $transaction = Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'recurring',
            'amount' => 25.00,
        ]);

        $result = UserSubscriptionService::processTransaction($transaction);

        expect($result)->toBeTrue()
            ->and($this->user->fresh()->hasRole('sustaining member'))->toBeTrue();
    });

    it('gets subscription stats', function () {
        // Create some test data
        User::factory()->count(5)->create();
        $sustainingUser = User::factory()->create();
        $sustainingUser->assignRole('sustaining member');

        Transaction::factory()->create([
            'type' => 'recurring',
            'amount' => 25.00,
            'created_at' => now()->subDays(15),
        ]);

        $stats = UserSubscriptionService::getSubscriptionStats();

        expect($stats)->toHaveKeys([
            'total_users',
            'sustaining_members',
            'sustaining_percentage',
            'monthly_revenue',
            'average_subscription',
            'total_free_hours_allocated'
        ]);
    });

    it('upgrades user to sustaining member', function () {
        $transaction = Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'recurring',
            'amount' => 25.00,
        ]);

        $result = UserSubscriptionService::upgradeToSustainingMember($this->user, $transaction);

        expect($result)->toBeTrue()
            ->and($this->user->fresh()->hasRole('sustaining member'))->toBeTrue();
    });

    it('does not upgrade already sustaining member', function () {
        $this->user->assignRole('sustaining member');

        $transaction = Transaction::factory()->create([
            'email' => $this->user->email,
            'type' => 'recurring',
            'amount' => 25.00,
        ]);

        $result = UserSubscriptionService::upgradeToSustainingMember($this->user, $transaction);

        expect($result)->toBeFalse(); // Already had the role
    });
});
