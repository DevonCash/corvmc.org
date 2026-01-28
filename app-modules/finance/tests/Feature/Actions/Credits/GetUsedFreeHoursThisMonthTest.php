<?php

use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Models\CreditTransaction;
use CorvMC\Finance\Models\UserCredit;
use App\Models\User;

// Block conversion: 1 block = 30 minutes, so 2 blocks = 1 hour

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->assignRole('sustaining member');

    // Initialize user credits (8 blocks = 4 hours)
    UserCredit::create([
        'user_id' => $this->user->id,
        'credit_type' => CreditType::FreeHours->value,
        'balance' => 8,
    ]);
});

it('returns zero for non-sustaining members', function () {
    $regularUser = User::factory()->create();

    expect($regularUser->getUsedFreeHoursThisMonth())->toBe(0.0);
});

it('counts deductions from charge_usage source', function () {
    // Create a charge usage deduction (2 hours = 4 blocks)
    CreditTransaction::create([
        'user_id' => $this->user->id,
        'credit_type' => CreditType::FreeHours->value,
        'amount' => -4,
        'balance_after' => 4,
        'source' => 'charge_usage',
        'source_id' => 1,
        'description' => 'Reservation charge',
        'created_at' => now(),
    ]);

    expect($this->user->getUsedFreeHoursThisMonth())->toBe(2.0);
});

it('accounts for refunds from charge_cancellation source', function () {
    // Create a charge usage deduction (2 hours = 4 blocks)
    CreditTransaction::create([
        'user_id' => $this->user->id,
        'credit_type' => CreditType::FreeHours->value,
        'amount' => -4,
        'balance_after' => 4,
        'source' => 'charge_usage',
        'source_id' => 1,
        'description' => 'Reservation charge',
        'created_at' => now(),
    ]);

    // Create a refund for the same charge (2 hours = 4 blocks)
    CreditTransaction::create([
        'user_id' => $this->user->id,
        'credit_type' => CreditType::FreeHours->value,
        'amount' => 4,
        'balance_after' => 8,
        'source' => 'charge_cancellation',
        'source_id' => 1,
        'description' => 'Refund for cancelled charge',
        'created_at' => now(),
    ]);

    // Net usage should be 0 since deduction was fully refunded
    expect($this->user->getUsedFreeHoursThisMonth())->toBe(0.0);
});

it('calculates net usage when partially refunded', function () {
    // Use 3 hours (6 blocks)
    CreditTransaction::create([
        'user_id' => $this->user->id,
        'credit_type' => CreditType::FreeHours->value,
        'amount' => -6,
        'balance_after' => 2,
        'source' => 'charge_usage',
        'source_id' => 1,
        'description' => 'Reservation charge',
        'created_at' => now(),
    ]);

    // Refund 1 hour (2 blocks)
    CreditTransaction::create([
        'user_id' => $this->user->id,
        'credit_type' => CreditType::FreeHours->value,
        'amount' => 2,
        'balance_after' => 4,
        'source' => 'charge_cancellation',
        'source_id' => 1,
        'description' => 'Partial refund',
        'created_at' => now(),
    ]);

    // Net usage should be 2 hours (3 used - 1 refunded)
    expect($this->user->getUsedFreeHoursThisMonth())->toBe(2.0);
});

it('ignores transactions from other sources', function () {
    // Create an admin adjustment (not from charges)
    CreditTransaction::create([
        'user_id' => $this->user->id,
        'credit_type' => CreditType::FreeHours->value,
        'amount' => -4,
        'balance_after' => 4,
        'source' => 'admin_adjustment',
        'description' => 'Admin deduction',
        'created_at' => now(),
    ]);

    // This should not be counted as "used" since it's not from a charge
    expect($this->user->getUsedFreeHoursThisMonth())->toBe(0.0);
});

it('ignores transactions from other months', function () {
    // Create a charge usage deduction from last month
    CreditTransaction::create([
        'user_id' => $this->user->id,
        'credit_type' => CreditType::FreeHours->value,
        'amount' => -4,
        'balance_after' => 4,
        'source' => 'charge_usage',
        'source_id' => 1,
        'description' => 'Reservation charge',
        'created_at' => now()->subMonth(),
    ]);

    // Should not count last month's usage
    expect($this->user->getUsedFreeHoursThisMonth())->toBe(0.0);
});
