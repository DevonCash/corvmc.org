<?php

use App\Actions\Credits\RedeemPromoCode;
use App\Enums\CreditType;
use App\Exceptions\PromoCodeAlreadyRedeemedException;
use App\Exceptions\PromoCodeMaxUsesException;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('redeems a valid promo code for free hours', function () {
    $user = User::factory()->create();
    $promo = PromoCode::create([
        'code' => 'FREEHOURS10',
        'credit_type' => CreditType::FreeHours->value,
        'credit_amount' => 10,
        'is_active' => true,
    ]);

    RedeemPromoCode::run($user, 'FREEHOURS10');

    expect($user->getCreditBalance(CreditType::FreeHours))->toBe(10);
});

it('redeems a valid promo code for equipment credits', function () {
    $user = User::factory()->create();
    $promo = PromoCode::create([
        'code' => 'EQUIPMENT5',
        'credit_type' => CreditType::EquipmentCredits->value,
        'credit_amount' => 5,
        'is_active' => true,
    ]);

    RedeemPromoCode::run($user, 'EQUIPMENT5');

    expect($user->getCreditBalance(CreditType::EquipmentCredits))->toBe(5);
});

it('creates a credit transaction with promo_code source', function () {
    $user = User::factory()->create();
    $promo = PromoCode::create([
        'code' => 'PROMO20',
        'credit_type' => CreditType::FreeHours->value,
        'credit_amount' => 20,
        'is_active' => true,
    ]);

    $transaction = RedeemPromoCode::run($user, 'PROMO20');

    expect($transaction)->not->toBeNull()
        ->and($transaction->source)->toBe('promo_code')
        ->and($transaction->amount)->toBe(20)
        ->and($transaction->description)->toContain('Promo code: PROMO20');
});

it('creates a redemption record', function () {
    $user = User::factory()->create();
    $promo = PromoCode::create([
        'code' => 'TESTCODE',
        'credit_type' => CreditType::FreeHours->value,
        'credit_amount' => 15,
        'is_active' => true,
    ]);

    $transaction = RedeemPromoCode::run($user, 'TESTCODE');

    $redemption = PromoCodeRedemption::where('user_id', $user->id)
        ->where('promo_code_id', $promo->id)
        ->first();

    expect($redemption)->not->toBeNull()
        ->and($redemption->credit_transaction_id)->toBe($transaction->id)
        ->and($redemption->redeemed_at)->not->toBeNull();
});

it('increments promo code uses count', function () {
    $user = User::factory()->create();
    $promo = PromoCode::create([
        'code' => 'USECOUNT',
        'credit_type' => CreditType::FreeHours->value,
        'credit_amount' => 10,
        'is_active' => true,
        'uses_count' => 0,
    ]);

    RedeemPromoCode::run($user, 'USECOUNT');

    expect($promo->fresh()->uses_count)->toBe(1);
});

it('throws exception when promo code already redeemed by same user', function () {
    $user = User::factory()->create();
    $promo = PromoCode::create([
        'code' => 'ONCEONLY',
        'credit_type' => CreditType::FreeHours->value,
        'credit_amount' => 10,
        'is_active' => true,
    ]);

    // First redemption succeeds
    RedeemPromoCode::run($user, 'ONCEONLY');

    expect($user->getCreditBalance(CreditType::FreeHours))->toBe(10);

    // Second redemption should fail
    RedeemPromoCode::run($user, 'ONCEONLY');
})->throws(PromoCodeAlreadyRedeemedException::class);

it('allows different users to redeem the same code', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $promo = PromoCode::create([
        'code' => 'MULTIUSER',
        'credit_type' => CreditType::FreeHours->value,
        'credit_amount' => 10,
        'is_active' => true,
    ]);

    RedeemPromoCode::run($user1, 'MULTIUSER');
    RedeemPromoCode::run($user2, 'MULTIUSER');

    expect($user1->getCreditBalance(CreditType::FreeHours))->toBe(10)
        ->and($user2->getCreditBalance(CreditType::FreeHours))->toBe(10)
        ->and($promo->fresh()->uses_count)->toBe(2);
});

it('throws exception when max uses reached', function () {
    $promo = PromoCode::create([
        'code' => 'LIMITED',
        'credit_type' => CreditType::FreeHours->value,
        'credit_amount' => 10,
        'max_uses' => 2,
        'uses_count' => 2,
        'is_active' => true,
    ]);

    $user = User::factory()->create();

    RedeemPromoCode::run($user, 'LIMITED');
})->throws(PromoCodeMaxUsesException::class);

it('allows redemption when under max uses', function () {
    $promo = PromoCode::create([
        'code' => 'LIMITED10',
        'credit_type' => CreditType::FreeHours->value,
        'credit_amount' => 10,
        'max_uses' => 5,
        'uses_count' => 4,
        'is_active' => true,
    ]);

    $user = User::factory()->create();

    RedeemPromoCode::run($user, 'LIMITED10');

    expect($user->getCreditBalance(CreditType::FreeHours))->toBe(10)
        ->and($promo->fresh()->uses_count)->toBe(5);
});

it('throws exception for inactive promo code', function () {
    PromoCode::create([
        'code' => 'INACTIVE',
        'credit_type' => CreditType::FreeHours->value,
        'credit_amount' => 10,
        'is_active' => false,
    ]);

    $user = User::factory()->create();

    RedeemPromoCode::run($user, 'INACTIVE');
})->throws(ModelNotFoundException::class);

it('throws exception for expired promo code', function () {
    PromoCode::create([
        'code' => 'EXPIRED',
        'credit_type' => CreditType::FreeHours->value,
        'credit_amount' => 10,
        'is_active' => true,
        'expires_at' => now()->subDay(),
    ]);

    $user = User::factory()->create();

    RedeemPromoCode::run($user, 'EXPIRED');
})->throws(ModelNotFoundException::class);

it('allows redemption of promo code that has not expired', function () {
    PromoCode::create([
        'code' => 'NOTEXPIRED',
        'credit_type' => CreditType::FreeHours->value,
        'credit_amount' => 15,
        'is_active' => true,
        'expires_at' => now()->addDay(),
    ]);

    $user = User::factory()->create();

    RedeemPromoCode::run($user, 'NOTEXPIRED');

    expect($user->getCreditBalance(CreditType::FreeHours))->toBe(15);
});

it('allows redemption of promo code with null expiry', function () {
    PromoCode::create([
        'code' => 'NOEXPIRY',
        'credit_type' => CreditType::FreeHours->value,
        'credit_amount' => 12,
        'is_active' => true,
        'expires_at' => null,
    ]);

    $user = User::factory()->create();

    RedeemPromoCode::run($user, 'NOEXPIRY');

    expect($user->getCreditBalance(CreditType::FreeHours))->toBe(12);
});

it('throws exception for non-existent promo code', function () {
    $user = User::factory()->create();

    RedeemPromoCode::run($user, 'DOESNOTEXIST');
})->throws(ModelNotFoundException::class);

it('is case sensitive for promo codes', function () {
    PromoCode::create([
        'code' => 'CaseSensitive',
        'credit_type' => CreditType::FreeHours->value,
        'credit_amount' => 10,
        'is_active' => true,
    ]);

    $user = User::factory()->create();

    RedeemPromoCode::run($user, 'casesensitive');
})->throws(ModelNotFoundException::class);

it('uses database transaction for atomic redemption', function () {
    $user = User::factory()->create();
    $promo = PromoCode::create([
        'code' => 'ATOMIC',
        'credit_type' => CreditType::FreeHours->value,
        'credit_amount' => 10,
        'is_active' => true,
        'uses_count' => 0,
    ]);

    RedeemPromoCode::run($user, 'ATOMIC');

    // All operations should succeed together
    expect($user->getCreditBalance(CreditType::FreeHours))->toBe(10)
        ->and($promo->fresh()->uses_count)->toBe(1)
        ->and(PromoCodeRedemption::where('user_id', $user->id)->count())->toBe(1);
});

it('links transaction to promo code via source_id', function () {
    $user = User::factory()->create();
    $promo = PromoCode::create([
        'code' => 'LINKED',
        'credit_type' => CreditType::FreeHours->value,
        'credit_amount' => 10,
        'is_active' => true,
    ]);

    $transaction = RedeemPromoCode::run($user, 'LINKED');

    expect($transaction->source_id)->toBe($promo->id);
});

it('handles large credit amounts', function () {
    $user = User::factory()->create();
    PromoCode::create([
        'code' => 'BIGCREDITS',
        'credit_type' => CreditType::FreeHours->value,
        'credit_amount' => 1000,
        'is_active' => true,
    ]);

    RedeemPromoCode::run($user, 'BIGCREDITS');

    expect($user->getCreditBalance(CreditType::FreeHours))->toBe(1000);
});
