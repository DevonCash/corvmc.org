<?php

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\Models\Transaction;
use CorvMC\Finance\States\TransactionState\Cancelled;
use CorvMC\Finance\States\TransactionState\Cleared;
use CorvMC\Finance\States\TransactionState\Failed;
use CorvMC\Finance\States\TransactionState\Pending;
use CorvMC\SpaceManagement\Models\RehearsalReservation;

beforeEach(function () {
    $this->user = User::factory()->create();
});

function createPendingTransaction(object $test): Transaction
{
    $reservation = RehearsalReservation::factory()->create([
        'reservable_id' => $test->user->id,
        'reservable_type' => 'user',
        'reserved_at' => Carbon::tomorrow()->setTime(10, 0),
        'reserved_until' => Carbon::tomorrow()->setTime(12, 0),
    ]);

    $order = Order::create([
        'user_id' => $test->user->id,
        'total_amount' => 3000,
    ]);

    $lineItems = Finance::price([$reservation]);
    foreach ($lineItems as $lineItem) {
        $lineItem->order_id = $order->id;
        $lineItem->save();
    }

    return Transaction::create([
        'order_id' => $order->id,
        'user_id' => $test->user->id,
        'currency' => 'stripe',
        'amount' => 3000,
        'type' => 'payment',
        'metadata' => ['session_id' => 'cs_test_' . uniqid()],
    ]);
}

// =========================================================================
// Finance::fail()
// =========================================================================

describe('Finance::fail()', function () {
    it('transitions a Pending Transaction to Failed', function () {
        $txn = createPendingTransaction($this);

        expect($txn->status)->toBeInstanceOf(Pending::class);

        $result = Finance::fail($txn);

        expect($result->status)->toBeInstanceOf(Failed::class);
        expect($result->failed_at)->not->toBeNull();
    });

    it('is idempotent — returns unchanged if already Failed', function () {
        $txn = createPendingTransaction($this);

        $first = Finance::fail($txn);
        expect($first->status)->toBeInstanceOf(Failed::class);

        // Second call should not throw
        $second = Finance::fail($first);
        expect($second->status)->toBeInstanceOf(Failed::class);
    });

    it('is idempotent — returns unchanged if already Cleared', function () {
        $txn = createPendingTransaction($this);

        Finance::settle($txn, 'pi_test_123');
        $txn->refresh();
        expect($txn->status)->toBeInstanceOf(Cleared::class);

        // Fail should be a no-op on a Cleared transaction
        $result = Finance::fail($txn);
        expect($result->status)->toBeInstanceOf(Cleared::class);
    });

    it('is idempotent — returns unchanged if already Cancelled', function () {
        $txn = createPendingTransaction($this);

        $txn->status->transitionTo(Cancelled::class);
        $txn->update(['cancelled_at' => now()]);
        $txn->refresh();

        $result = Finance::fail($txn);
        expect($result->status)->toBeInstanceOf(Cancelled::class);
    });

    it('sets failed_at timestamp', function () {
        $txn = createPendingTransaction($this);

        expect($txn->failed_at)->toBeNull();

        $result = Finance::fail($txn);

        expect($result->failed_at)->not->toBeNull();
        expect($result->failed_at->diffInSeconds(now()))->toBeLessThan(5);
    });
});
