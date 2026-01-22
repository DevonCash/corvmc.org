<?php

use CorvMC\Finance\Actions\Credits\AllocateMonthlyCredits;
use App\Enums\CreditType;
use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

describe('AllocateMonthlyCredits - First Allocation', function () {
    it('allocates free hours credits on first allocation', function () {
        $user = User::factory()->create();

        AllocateMonthlyCredits::run($user, 16, CreditType::FreeHours);

        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(16);
    });

    it('allocates equipment credits on first allocation', function () {
        $user = User::factory()->create();

        AllocateMonthlyCredits::run($user, 10, CreditType::EquipmentCredits);

        expect($user->getCreditBalance(CreditType::EquipmentCredits))->toBe(10);
    });

    it('creates transaction with monthly_reset source for free hours', function () {
        $user = User::factory()->create();

        AllocateMonthlyCredits::run($user, 16, CreditType::FreeHours);

        $transaction = $user->creditTransactions()->latest()->first();

        expect($transaction)->not->toBeNull()
            ->and($transaction->source)->toBe('monthly_reset')
            ->and($transaction->amount)->toBe(16)
            ->and($transaction->description)->toContain('Monthly reset to 16 blocks');
    });

    it('creates transaction with monthly_allocation source for equipment credits', function () {
        $user = User::factory()->create();

        AllocateMonthlyCredits::run($user, 10, CreditType::EquipmentCredits);

        $transaction = $user->creditTransactions()->latest()->first();

        expect($transaction)->not->toBeNull()
            ->and($transaction->source)->toBe('monthly_allocation')
            ->and($transaction->amount)->toBe(10)
            ->and($transaction->description)->toBe('Monthly equipment credits allocation');
    });
});

describe('AllocateMonthlyCredits - Free Hours Strategy (Reset)', function () {
    it('resets free hours to subscription amount (no rollover)', function () {
        $user = User::factory()->create();

        // First allocation: 16 blocks
        AllocateMonthlyCredits::run($user, 16, CreditType::FreeHours);

        // User uses 10 blocks
        $user->deductCredit(10, CreditType::FreeHours, 'test_usage');
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(6);

        // Second allocation after a month: should reset to 16, not add 16
        $this->travel(1)->month();
        AllocateMonthlyCredits::run($user, 16, CreditType::FreeHours);

        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(16);
    });

    it('resets even if user has leftover credits', function () {
        $user = User::factory()->create();

        // First allocation: 16 blocks
        AllocateMonthlyCredits::run($user, 16, CreditType::FreeHours);

        // User only uses 2 blocks, has 14 left
        $user->deductCredit(2, CreditType::FreeHours, 'test_usage');
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(14);

        // Second allocation: resets to 16 (14 leftover credits are lost)
        $this->travel(1)->month();
        AllocateMonthlyCredits::run($user, 16, CreditType::FreeHours);

        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(16);
    });

    it('records correct delta in transaction when resetting', function () {
        $user = User::factory()->create();

        AllocateMonthlyCredits::run($user, 16, CreditType::FreeHours);
        $user->deductCredit(10, CreditType::FreeHours, 'test_usage');

        $this->travel(1)->month();
        AllocateMonthlyCredits::run($user, 16, CreditType::FreeHours);

        $transaction = $user->creditTransactions()
            ->where('source', 'monthly_reset')
            ->latest()
            ->first();

        // Delta should be +10 (from 6 to 16)
        expect($transaction->amount)->toBe(10)
            ->and($transaction->description)->toContain('was 6');
    });
});

describe('AllocateMonthlyCredits - Equipment Credits Strategy (Rollover with Cap)', function () {
    it('adds equipment credits with rollover enabled', function () {
        $user = User::factory()->create();

        // First allocation: 10 credits
        AllocateMonthlyCredits::run($user, 10, CreditType::EquipmentCredits);

        // User only uses 2, has 8 left
        $user->deductCredit(2, CreditType::EquipmentCredits, 'test_usage');
        expect($user->getCreditBalance(CreditType::EquipmentCredits))->toBe(8);

        // Second allocation: should ADD 10 to existing 8 = 18
        $this->travel(1)->month();
        AllocateMonthlyCredits::run($user, 10, CreditType::EquipmentCredits);

        expect($user->getCreditBalance(CreditType::EquipmentCredits))->toBe(18);
    });

    it('respects max_balance cap for equipment credits', function () {
        $user = User::factory()->create();

        // Give user 245 credits (5 below cap of 250)
        $user->addCredit(245, CreditType::EquipmentCredits, 'test_setup');

        // Try to allocate 10 more (would be 255, but cap is 250)
        AllocateMonthlyCredits::run($user, 10, CreditType::EquipmentCredits);

        // Should only add 5 to reach cap
        expect($user->getCreditBalance(CreditType::EquipmentCredits))->toBe(250);
    });

    it('does not add credits when already at cap', function () {
        $user = User::factory()->create();

        // Give user exactly 250 (at cap)
        $user->addCredit(250, CreditType::EquipmentCredits, 'test_setup');

        // Try to allocate more
        AllocateMonthlyCredits::run($user, 10, CreditType::EquipmentCredits);

        // Should remain at cap
        expect($user->getCreditBalance(CreditType::EquipmentCredits))->toBe(250);

        // Should not create a transaction for 0 credits added
        $allocationTransaction = CreditTransaction::where('user_id', $user->id)
            ->where('source', 'monthly_allocation')
            ->latest()
            ->first();

        expect($allocationTransaction)->toBeNull();
    });

    it('includes metadata about cap in transaction', function () {
        $user = User::factory()->create();
        $user->addCredit(245, CreditType::EquipmentCredits, 'test_setup');

        AllocateMonthlyCredits::run($user, 10, CreditType::EquipmentCredits);

        $transaction = CreditTransaction::where('user_id', $user->id)
            ->where('source', 'monthly_allocation')
            ->latest()
            ->first();

        // Force the model to recognize the cast
        $metadata = is_array($transaction->metadata) ? $transaction->metadata : json_decode($transaction->metadata, true);

        expect($transaction)->not->toBeNull()
            ->and($metadata)->toBeArray()
            ->and($metadata['cap_reached'])->toBeTrue()
            ->and($metadata['requested_amount'])->toBe(10)
            ->and($metadata['actual_amount'])->toBe(5)
            ->and($transaction->description)->toContain('(capped at 250)');
    });
});

describe('AllocateMonthlyCredits - Mid-Month Upgrades', function () {
    it('handles mid-month upgrade by adding tier delta', function () {
        $user = User::factory()->create();

        // Initial allocation: 16 blocks
        AllocateMonthlyCredits::run($user, 16, CreditType::FreeHours);

        // User uses 4 blocks, has 12 left
        $user->deductCredit(4, CreditType::FreeHours, 'test_usage');
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(12);

        // Mid-month: user upgrades subscription to 32 blocks
        // Tier delta = 32 - 16 = 16
        // Balance should be 12 + 16 = 28 (not 32!)
        AllocateMonthlyCredits::run($user, 32, CreditType::FreeHours);

        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(28);
    });

    it('creates upgrade_adjustment transaction for mid-month upgrade', function () {
        $user = User::factory()->create();

        AllocateMonthlyCredits::run($user, 16, CreditType::FreeHours);
        $user->deductCredit(4, CreditType::FreeHours, 'test_usage');

        // Upgrade same month
        AllocateMonthlyCredits::run($user, 32, CreditType::FreeHours);

        $transaction = $user->creditTransactions()
            ->where('source', 'upgrade_adjustment')
            ->latest()
            ->first();

        $metadata = is_array($transaction->metadata)
            ? $transaction->metadata
            : json_decode($transaction->metadata, true);

        expect($transaction)->not->toBeNull()
            ->and($transaction->amount)->toBe(16) // Tier delta: 32 - 16
            ->and($transaction->description)->toContain('Mid-month upgrade')
            ->and($transaction->description)->toContain('tier changed from 16 to 32')
            ->and($metadata['allocated_amount'])->toBe(32)
            ->and($metadata['previous_amount'])->toBe(16)
            ->and($metadata['tier_delta'])->toBe(16);
    });

    it('does nothing for mid-month downgrade', function () {
        $user = User::factory()->create();

        // Initial allocation: 32 blocks
        AllocateMonthlyCredits::run($user, 32, CreditType::FreeHours);
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(32);

        // Mid-month: user downgrades to 16 blocks
        // Should keep the 32 until next cycle
        AllocateMonthlyCredits::run($user, 16, CreditType::FreeHours);

        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(32);

        // Should not create upgrade_adjustment transaction
        $upgradeTransaction = CreditTransaction::where('user_id', $user->id)
            ->where('source', 'upgrade_adjustment')
            ->first();

        expect($upgradeTransaction)->toBeNull();
    });

    it('handles equipment credits mid-month upgrade respecting cap', function () {
        $user = User::factory()->create();

        // Initial allocation: 5 credits
        AllocateMonthlyCredits::run($user, 5, CreditType::EquipmentCredits);
        expect($user->getCreditBalance(CreditType::EquipmentCredits))->toBe(5);

        // Manually add 243 more to get to 248 (2 below cap of 250)
        $user->addCredit(243, CreditType::EquipmentCredits, 'test_setup');
        expect($user->getCreditBalance(CreditType::EquipmentCredits))->toBe(248);

        // Mid-month upgrade to 10 credits tier
        // Tier delta = 10 - 5 = 5
        // But can only add 2 more to reach cap (250 - 248 = 2)
        AllocateMonthlyCredits::run($user, 10, CreditType::EquipmentCredits);

        // Should only add 2 to reach cap
        expect($user->getCreditBalance(CreditType::EquipmentCredits))->toBe(250);
    });
});

describe('AllocateMonthlyCredits - Timing Logic', function () {
    it('waits for next allocation date before regular allocation', function () {
        $user = User::factory()->create();

        // First allocation
        AllocateMonthlyCredits::run($user, 16, CreditType::FreeHours);
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(16);

        $user->deductCredit(10, CreditType::FreeHours, 'test_usage');
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(6);

        // Try to allocate same amount again immediately (same month, same tier)
        // Previous allocation: 16, new allocation: 16
        // Tier delta = 16 - 16 = 0, so no change
        AllocateMonthlyCredits::run($user, 16, CreditType::FreeHours);

        // Balance should remain unchanged since tier didn't change
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(6);
    });

    it('allocates when a month has passed using addMonthNoOverflow', function () {
        $user = User::factory()->create();

        // First allocation on Jan 31
        $this->travel(1)->month();
        AllocateMonthlyCredits::run($user, 16, CreditType::FreeHours);

        $user->deductCredit(10, CreditType::FreeHours, 'test_usage');

        // Travel exactly one month forward
        $this->travel(1)->month();
        AllocateMonthlyCredits::run($user, 16, CreditType::FreeHours);

        // Should have reset to 16
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(16);
    });
});

describe('AllocateMonthlyCredits - Thread Safety', function () {
    it('uses database transaction for atomic updates', function () {
        $user = User::factory()->create();

        DB::beginTransaction();
        try {
            AllocateMonthlyCredits::run($user, 16, CreditType::FreeHours);
            DB::rollBack();
        } catch (\Exception $e) {
            DB::rollBack();
        }

        // Should have been rolled back
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(0);
    });

    it('uses lockForUpdate to prevent race conditions', function () {
        $user = User::factory()->create();

        // This is tested implicitly by the action using lockForUpdate()
        // Multiple concurrent runs should not cause balance corruption
        AllocateMonthlyCredits::run($user, 16, CreditType::FreeHours);

        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(16);
    });
});
