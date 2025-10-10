<?php

use App\Models\User;
use App\Models\UserCredit;
use App\Models\CreditTransaction;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Actions\Credits\GetBalance;
use App\Actions\Credits\AddCredits;
use App\Actions\Credits\DeductCredits;
use App\Actions\Credits\AllocateMonthlyCredits;
use App\Actions\Credits\RedeemPromoCode;
use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\PromoCodeAlreadyRedeemedException;
use App\Exceptions\PromoCodeMaxUsesException;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('GetBalance', function () {
    it('returns 0 for user with no credits', function () {
        $balance = GetBalance::run($this->user, 'free_hours');

        expect($balance)->toBe(0);
    });

    it('returns correct balance for user with credits', function () {
        UserCredit::create([
            'user_id' => $this->user->id,
            'credit_type' => 'free_hours',
            'balance' => 100,
        ]);

        $balance = GetBalance::run($this->user, 'free_hours');

        expect($balance)->toBe(100);
    });

    it('excludes expired credits', function () {
        UserCredit::create([
            'user_id' => $this->user->id,
            'credit_type' => 'free_hours',
            'balance' => 100,
            'expires_at' => now()->subDay(),
        ]);

        $balance = GetBalance::run($this->user, 'free_hours');

        expect($balance)->toBe(0);
    });
});

describe('AddCredits', function () {
    it('creates credit record if none exists', function () {
        $transaction = AddCredits::run(
            $this->user,
            100,
            'test_source',
            null,
            'Test credit addition'
        );

        expect($transaction)->toBeInstanceOf(CreditTransaction::class);
        expect($transaction->amount)->toBe(100);
        expect($transaction->balance_after)->toBe(100);

        $credit = UserCredit::where('user_id', $this->user->id)
            ->where('credit_type', 'free_hours')
            ->first();

        expect($credit->balance)->toBe(100);
    });

    it('adds to existing balance', function () {
        UserCredit::create([
            'user_id' => $this->user->id,
            'credit_type' => 'free_hours',
            'balance' => 50,
        ]);

        $transaction = AddCredits::run($this->user, 100, 'test_source');

        expect($transaction->balance_after)->toBe(150);

        $credit = UserCredit::where('user_id', $this->user->id)
            ->where('credit_type', 'free_hours')
            ->first();

        expect($credit->balance)->toBe(150);
    });

    it('records transaction with all details', function () {
        $transaction = AddCredits::run(
            $this->user,
            100,
            'subscription',
            123,
            'Monthly allocation',
            'free_hours'
        );

        expect($transaction->user_id)->toBe($this->user->id);
        expect($transaction->credit_type)->toBe('free_hours');
        expect($transaction->source)->toBe('subscription');
        expect($transaction->source_id)->toBe(123);
        expect($transaction->description)->toBe('Monthly allocation');
    });
});

describe('DeductCredits', function () {
    it('deducts credits from balance', function () {
        UserCredit::create([
            'user_id' => $this->user->id,
            'credit_type' => 'free_hours',
            'balance' => 100,
        ]);

        $transaction = DeductCredits::run($this->user, 30, 'reservation', 456);

        expect($transaction->amount)->toBe(-30);
        expect($transaction->balance_after)->toBe(70);

        $credit = UserCredit::where('user_id', $this->user->id)
            ->where('credit_type', 'free_hours')
            ->first();

        expect($credit->balance)->toBe(70);
    });

    it('throws exception when insufficient credits', function () {
        UserCredit::create([
            'user_id' => $this->user->id,
            'credit_type' => 'free_hours',
            'balance' => 20,
        ]);

        DeductCredits::run($this->user, 30, 'reservation');
    })->throws(InsufficientCreditsException::class);

    it('throws exception when no credit record exists', function () {
        DeductCredits::run($this->user, 30, 'reservation');
    })->throws(InsufficientCreditsException::class);
});

describe('AllocateMonthlyCredits', function () {
    afterEach(function () {
        Cache::flush();
    });

    it('resets free_hours balance (no rollover)', function () {
        UserCredit::create([
            'user_id' => $this->user->id,
            'credit_type' => 'free_hours',
            'balance' => 50,
        ]);

        AllocateMonthlyCredits::run($this->user, 100, 'free_hours');

        $credit = UserCredit::where('user_id', $this->user->id)
            ->where('credit_type', 'free_hours')
            ->first();

        expect($credit->balance)->toBe(100);

        $transaction = CreditTransaction::where('user_id', $this->user->id)
            ->where('source', 'monthly_reset')
            ->first();

        expect($transaction)->not->toBeNull();
        expect($transaction->amount)->toBe(100);
    });

    it('adds equipment_credits with rollover', function () {
        UserCredit::create([
            'user_id' => $this->user->id,
            'credit_type' => 'equipment_credits',
            'balance' => 50,
            'max_balance' => 250,
            'rollover_enabled' => true,
        ]);

        AllocateMonthlyCredits::run($this->user, 100, 'equipment_credits');

        $credit = UserCredit::where('user_id', $this->user->id)
            ->where('credit_type', 'equipment_credits')
            ->first();

        expect($credit->balance)->toBe(150); // 50 + 100
    });

    it('respects max_balance cap for equipment credits', function () {
        UserCredit::create([
            'user_id' => $this->user->id,
            'credit_type' => 'equipment_credits',
            'balance' => 230,
            'max_balance' => 250,
            'rollover_enabled' => true,
        ]);

        AllocateMonthlyCredits::run($this->user, 100, 'equipment_credits');

        $credit = UserCredit::where('user_id', $this->user->id)
            ->where('credit_type', 'equipment_credits')
            ->first();

        expect($credit->balance)->toBe(250); // Capped at max_balance, only 20 added

        $transaction = CreditTransaction::where('user_id', $this->user->id)
            ->where('source', 'monthly_allocation')
            ->first();

        expect($transaction->amount)->toBe(20);
    });

    it('is idempotent - does not double allocate in same month', function () {
        AllocateMonthlyCredits::run($this->user, 100, 'free_hours');
        AllocateMonthlyCredits::run($this->user, 100, 'free_hours');

        $credit = UserCredit::where('user_id', $this->user->id)
            ->where('credit_type', 'free_hours')
            ->first();

        expect($credit->balance)->toBe(100); // Not 200

        $transactionCount = CreditTransaction::where('user_id', $this->user->id)
            ->where('source', 'monthly_reset')
            ->count();

        expect($transactionCount)->toBe(1);
    });
});

describe('RedeemPromoCode', function () {
    beforeEach(function () {
        $this->promo = PromoCode::create([
            'code' => 'TEST100',
            'credit_amount' => 100,
            'credit_type' => 'free_hours',
            'is_active' => true,
            'max_uses' => 10,
            'uses_count' => 0,
        ]);
    });

    it('successfully redeems valid promo code', function () {
        $transaction = RedeemPromoCode::run($this->user, 'TEST100');

        expect($transaction)->toBeInstanceOf(CreditTransaction::class);
        expect($transaction->amount)->toBe(100);
        expect($transaction->source)->toBe('promo_code');

        $redemption = PromoCodeRedemption::where('user_id', $this->user->id)
            ->where('promo_code_id', $this->promo->id)
            ->first();

        expect($redemption)->not->toBeNull();

        $this->promo->refresh();
        expect($this->promo->uses_count)->toBe(1);
    });

    it('throws exception when code already redeemed', function () {
        RedeemPromoCode::run($this->user, 'TEST100');
        RedeemPromoCode::run($this->user, 'TEST100');
    })->throws(PromoCodeAlreadyRedeemedException::class);

    it('throws exception when max uses reached', function () {
        $this->promo->update(['max_uses' => 1, 'uses_count' => 1]);

        $newUser = User::factory()->create();
        RedeemPromoCode::run($newUser, 'TEST100');
    })->throws(PromoCodeMaxUsesException::class);

    it('ignores expired promo codes', function () {
        $this->promo->update(['expires_at' => now()->subDay()]);

        RedeemPromoCode::run($this->user, 'TEST100');
    })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    it('ignores inactive promo codes', function () {
        $this->promo->update(['is_active' => false]);

        RedeemPromoCode::run($this->user, 'TEST100');
    })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});
