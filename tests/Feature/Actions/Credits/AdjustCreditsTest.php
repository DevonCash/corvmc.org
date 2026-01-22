<?php

use CorvMC\Finance\Actions\Credits\AdjustCredits;
use App\Enums\CreditType;
use App\Models\User;

it('adds free hours credits to a user', function () {
    $user = User::factory()->create();

    AdjustCredits::run($user, 10, CreditType::FreeHours);

    expect($user->getCreditBalance(CreditType::FreeHours))->toBe(10);
});

it('adds equipment credits to a user', function () {
    $user = User::factory()->create();

    AdjustCredits::run($user, 5, CreditType::EquipmentCredits);

    expect($user->getCreditBalance(CreditType::EquipmentCredits))->toBe(5);
});

it('deducts free hours credits from a user', function () {
    $user = User::factory()->create();
    AdjustCredits::run($user, 20, CreditType::FreeHours);

    AdjustCredits::run($user, -5, CreditType::FreeHours);

    expect($user->getCreditBalance(CreditType::FreeHours))->toBe(15);
});

it('deducts equipment credits from a user', function () {
    $user = User::factory()->create();
    AdjustCredits::run($user, 15, CreditType::EquipmentCredits);

    AdjustCredits::run($user, -3, CreditType::EquipmentCredits);

    expect($user->getCreditBalance(CreditType::EquipmentCredits))->toBe(12);
});

it('creates a transaction record with correct source', function () {
    $user = User::factory()->create();

    AdjustCredits::run($user, 8, CreditType::FreeHours);

    $transaction = $user->creditTransactions()->latest()->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->amount)->toBe(8)
        ->and($transaction->source)->toBe('admin_adjustment')
        ->and($transaction->credit_type)->toBe('free_hours')
        ->and($transaction->balance_after)->toBe(8);
});

it('can adjust credits multiple times', function () {
    $user = User::factory()->create();

    AdjustCredits::run($user, 5, CreditType::FreeHours);
    AdjustCredits::run($user, 3, CreditType::FreeHours);
    AdjustCredits::run($user, -2, CreditType::FreeHours);

    expect($user->getCreditBalance(CreditType::FreeHours))->toBe(6)
        ->and($user->creditTransactions()->count())->toBe(3);
});

it('handles negative balance when deducting more than available', function () {
    $user = User::factory()->create();
    AdjustCredits::run($user, 5, CreditType::FreeHours);

    AdjustCredits::run($user, -10, CreditType::FreeHours);

    expect($user->getCreditBalance(CreditType::FreeHours))->toBe(-5);
});

it('defaults to FreeHours credit type when not specified', function () {
    $user = User::factory()->create();

    AdjustCredits::run($user, 7);

    expect($user->getCreditBalance(CreditType::FreeHours))->toBe(7)
        ->and($user->getCreditBalance(CreditType::EquipmentCredits))->toBe(0);
});
