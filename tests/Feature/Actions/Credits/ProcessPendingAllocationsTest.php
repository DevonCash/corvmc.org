<?php

use CorvMC\Finance\Actions\Credits\ProcessPendingAllocations;
use App\Enums\CreditType;
use App\Models\CreditAllocation;
use App\Models\User;

it('processes pending monthly allocations', function () {
    $user = User::factory()->create();

    // Create pending allocation due now
    $allocation = CreditAllocation::create([
        'user_id' => $user->id,
        'credit_type' => CreditType::FreeHours->value,
        'amount' => 16,
        'frequency' => 'monthly',
        'source' => 'subscription',
        'is_active' => true,
        'starts_at' => now()->subMonth(),
        'next_allocation_at' => now()->subDay(),
    ]);

    ProcessPendingAllocations::run();

    expect($user->getCreditBalance(CreditType::FreeHours))->toBe(16)
        ->and($allocation->fresh()->last_allocated_at)->not->toBeNull();
});

it('skips allocations not yet due', function () {
    $user = User::factory()->create();

    $allocation = CreditAllocation::create([
        'user_id' => $user->id,
        'credit_type' => CreditType::FreeHours->value,
        'amount' => 16,
        'frequency' => 'monthly',
        'source' => 'subscription',
        'is_active' => true,
        'starts_at' => now(),
        'next_allocation_at' => now()->addDay(), // Tomorrow
    ]);

    ProcessPendingAllocations::run();

    expect($user->getCreditBalance(CreditType::FreeHours))->toBe(0)
        ->and($allocation->fresh()->last_allocated_at)->toBeNull();
});

it('skips inactive allocations', function () {
    $user = User::factory()->create();

    CreditAllocation::create([
        'user_id' => $user->id,
        'credit_type' => CreditType::FreeHours->value,
        'amount' => 16,
        'frequency' => 'monthly',
        'source' => 'subscription',
        'is_active' => false,
        'starts_at' => now()->subMonth(),
        'next_allocation_at' => now()->subDay(),
    ]);

    ProcessPendingAllocations::run();

    expect($user->getCreditBalance(CreditType::FreeHours))->toBe(0);
});

it('updates next_allocation_at for monthly frequency', function () {
    $user = User::factory()->create();
    $originalNextDate = now()->subDay();

    $allocation = CreditAllocation::create([
        'user_id' => $user->id,
        'credit_type' => CreditType::FreeHours->value,
        'amount' => 16,
        'frequency' => 'monthly',
        'source' => 'subscription',
        'is_active' => true,
        'starts_at' => now()->subMonth(),
        'next_allocation_at' => $originalNextDate,
    ]);

    ProcessPendingAllocations::run();

    $allocation->refresh();

    expect($allocation->next_allocation_at->greaterThan($originalNextDate))->toBeTrue()
        ->and($allocation->next_allocation_at->month)->toBe(now()->addMonth()->startOfMonth()->month);
});

it('updates next_allocation_at for weekly frequency', function () {
    $user = User::factory()->create();
    $originalNextDate = now()->subDay();

    $allocation = CreditAllocation::create([
        'user_id' => $user->id,
        'credit_type' => CreditType::FreeHours->value,
        'amount' => 4,
        'frequency' => 'weekly',
        'source' => 'subscription',
        'is_active' => true,
        'starts_at' => now()->subWeek(),
        'next_allocation_at' => $originalNextDate,
    ]);

    ProcessPendingAllocations::run();

    $allocation->refresh();

    // Check that next_allocation_at is approximately 7 days from now (within a day)
    $daysUntilNext = now()->diffInDays($allocation->next_allocation_at, false);

    expect($daysUntilNext)->toBeGreaterThanOrEqual(6)
        ->and($daysUntilNext)->toBeLessThanOrEqual(8);
});

it('handles one_time frequency by setting far future date', function () {
    $user = User::factory()->create();

    $allocation = CreditAllocation::create([
        'user_id' => $user->id,
        'credit_type' => CreditType::FreeHours->value,
        'amount' => 50,
        'frequency' => 'one_time',
        'source' => 'promotion',
        'is_active' => true,
        'starts_at' => now()->subWeek(),
        'next_allocation_at' => now()->subDay(),
    ]);

    ProcessPendingAllocations::run();

    $allocation->refresh();

    expect($user->getCreditBalance(CreditType::FreeHours))->toBe(50)
        ->and($allocation->next_allocation_at->year)->toBeGreaterThan(now()->year + 50);
});

it('processes multiple pending allocations', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    CreditAllocation::create([
        'user_id' => $user1->id,
        'credit_type' => CreditType::FreeHours->value,
        'amount' => 16,
        'frequency' => 'monthly',
        'source' => 'subscription',
        'is_active' => true,
        'starts_at' => now()->subMonth(),
        'next_allocation_at' => now()->subDay(),
    ]);

    CreditAllocation::create([
        'user_id' => $user2->id,
        'credit_type' => CreditType::EquipmentCredits->value,
        'amount' => 10,
        'frequency' => 'monthly',
        'source' => 'subscription',
        'is_active' => true,
        'starts_at' => now()->subMonth(),
        'next_allocation_at' => now()->subHour(),
    ]);

    ProcessPendingAllocations::run();

    expect($user1->getCreditBalance(CreditType::FreeHours))->toBe(16)
        ->and($user2->getCreditBalance(CreditType::EquipmentCredits))->toBe(10);
});

it('processes allocation with different credit types', function () {
    $user = User::factory()->create();

    CreditAllocation::create([
        'user_id' => $user->id,
        'credit_type' => CreditType::EquipmentCredits->value,
        'amount' => 5,
        'frequency' => 'monthly',
        'source' => 'subscription',
        'is_active' => true,
        'starts_at' => now()->subMonth(),
        'next_allocation_at' => now()->subDay(),
    ]);

    ProcessPendingAllocations::run();

    expect($user->getCreditBalance(CreditType::EquipmentCredits))->toBe(5)
        ->and($user->getCreditBalance(CreditType::FreeHours))->toBe(0);
});

it('uses AllocateMonthlyCredits action internally', function () {
    $user = User::factory()->create();

    // Create first allocation to establish history
    $allocation = CreditAllocation::create([
        'user_id' => $user->id,
        'credit_type' => CreditType::FreeHours->value,
        'amount' => 16,
        'frequency' => 'monthly',
        'source' => 'subscription',
        'is_active' => true,
        'starts_at' => now()->subMonth(),
        'next_allocation_at' => now()->subDay(),
    ]);

    ProcessPendingAllocations::run();

    // Check that transaction was created with proper source
    $transaction = $user->creditTransactions()
        ->where('source', 'monthly_reset')
        ->latest()
        ->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->amount)->toBe(16);
});

it('wraps allocation in database transaction', function () {
    $user = User::factory()->create();

    $allocation = CreditAllocation::create([
        'user_id' => $user->id,
        'credit_type' => CreditType::FreeHours->value,
        'amount' => 16,
        'frequency' => 'monthly',
        'source' => 'subscription',
        'is_active' => true,
        'starts_at' => now()->subMonth(),
        'next_allocation_at' => now()->subDay(),
    ]);

    ProcessPendingAllocations::run();

    // If transaction works correctly, both credit and allocation update should succeed
    $allocation->refresh();

    expect($user->getCreditBalance(CreditType::FreeHours))->toBe(16)
        ->and($allocation->last_allocated_at)->not->toBeNull();
});

it('returns early when no pending allocations exist', function () {
    // No allocations in database
    ProcessPendingAllocations::run();

    // Should complete without errors
    expect(true)->toBeTrue();
});

it('handles allocations exactly at next_allocation_at time', function () {
    $user = User::factory()->create();
    $exactTime = now();

    $allocation = CreditAllocation::create([
        'user_id' => $user->id,
        'credit_type' => CreditType::FreeHours->value,
        'amount' => 16,
        'frequency' => 'monthly',
        'source' => 'subscription',
        'is_active' => true,
        'starts_at' => now()->subMonth(),
        'next_allocation_at' => $exactTime,
    ]);

    ProcessPendingAllocations::run();

    expect($user->getCreditBalance(CreditType::FreeHours))->toBe(16);
});
