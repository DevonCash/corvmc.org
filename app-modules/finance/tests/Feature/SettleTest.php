<?php

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Finance\Events\TransactionCleared;
use CorvMC\Finance\Events\OrderSettled;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\Models\Transaction;
use CorvMC\Finance\States\OrderState\Completed;
use CorvMC\Finance\States\OrderState\Pending;
use CorvMC\Finance\States\TransactionState\Cleared;
use CorvMC\Finance\States\TransactionState\Pending as TransactionPending;
use CorvMC\SpaceManagement\Models\RehearsalReservation;

beforeEach(function () {
    $this->user = User::factory()->create();
});

/**
 * Create a committed Order with a Pending cash Transaction.
 */
function createCommittedOrder(object $test, int $amount = 3000): array
{
    $reservation = RehearsalReservation::factory()->create([
        'reservable_id' => $test->user->id,
        'reservable_type' => 'user',
        'reserved_at' => Carbon::tomorrow()->setTime(10, 0),
        'reserved_until' => Carbon::tomorrow()->setTime(12, 0),
    ]);

    $order = Order::create([
        'user_id' => $test->user->id,
        'total_amount' => 0,
    ]);

    $lineItems = Finance::price([$reservation]);
    foreach ($lineItems as $lineItem) {
        $lineItem->order_id = $order->id;
        $lineItem->save();
    }
    $order->update(['total_amount' => $lineItems->sum('amount')]);

    $committed = Finance::commit($order->fresh(), ['cash' => $amount]);

    return ['order' => $committed, 'reservation' => $reservation];
}

// =========================================================================
// Finance::settle() — happy path
// =========================================================================

describe('Finance::settle()', function () {
    it('transitions a Pending Transaction to Cleared', function () {
        ['order' => $order] = createCommittedOrder($this);

        $transaction = $order->transactions->first();
        expect($transaction->status)->toBeInstanceOf(TransactionPending::class);

        $settled = Finance::settle($transaction);

        expect($settled->status)->toBeInstanceOf(Cleared::class);
        expect($settled->cleared_at)->not->toBeNull();
    });

    it('stores payment_intent_id in metadata when provided', function () {
        ['order' => $order] = createCommittedOrder($this);

        $transaction = $order->transactions->first();

        $settled = Finance::settle($transaction, 'pi_test_123');

        expect($settled->metadata['payment_intent_id'])->toBe('pi_test_123');
    });

    it('preserves existing metadata when adding payment_intent_id', function () {
        ['order' => $order] = createCommittedOrder($this);

        $transaction = $order->transactions->first();
        $transaction->update(['metadata' => ['session_id' => 'cs_test_456']]);

        $settled = Finance::settle($transaction, 'pi_test_789');

        expect($settled->metadata['session_id'])->toBe('cs_test_456');
        expect($settled->metadata['payment_intent_id'])->toBe('pi_test_789');
    });

    it('fires TransactionCleared event', function () {
        \Illuminate\Support\Facades\Event::fake([TransactionCleared::class]);

        ['order' => $order] = createCommittedOrder($this);

        $transaction = $order->transactions->first();

        Finance::settle($transaction);

        \Illuminate\Support\Facades\Event::assertDispatched(TransactionCleared::class, function ($event) use ($transaction) {
            return $event->transaction->id === $transaction->id;
        });
    });
});

// =========================================================================
// Finance::settle() — validation
// =========================================================================

describe('Finance::settle() validation', function () {
    it('throws when Transaction is not Pending', function () {
        ['order' => $order] = createCommittedOrder($this);

        $transaction = $order->transactions->first();

        // Settle it once
        Finance::settle($transaction);

        // Try settling again
        expect(fn () => Finance::settle($transaction->fresh()))
            ->toThrow(\RuntimeException::class, 'expected Pending');
    });
});

// =========================================================================
// Order auto-completion via CheckOrderSettlement listener
// =========================================================================

describe('Order auto-completion on settlement', function () {
    it('transitions Order to Completed when single Transaction clears', function () {
        ['order' => $order] = createCommittedOrder($this);

        expect($order->status)->toBeInstanceOf(Pending::class);

        $transaction = $order->transactions->first();
        Finance::settle($transaction);

        $order->refresh();
        expect($order->status)->toBeInstanceOf(Completed::class);
        expect($order->settled_at)->not->toBeNull();
    });

    it('fires OrderSettled when Order transitions to Completed', function () {
        \Illuminate\Support\Facades\Event::fake([OrderSettled::class]);

        ['order' => $order] = createCommittedOrder($this);

        $transaction = $order->transactions->first();
        Finance::settle($transaction);

        \Illuminate\Support\Facades\Event::assertDispatched(OrderSettled::class);
    });

    it('keeps Order Pending when one of two Transactions is still unsettled', function () {
        ['order' => $order] = createCommittedOrder($this);

        // Add a second Pending Transaction (simulating split payment)
        $txn2 = Transaction::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'currency' => 'cash',
            'amount' => 500,
            'type' => 'payment',
            'metadata' => [],
        ]);

        // Settle only the first Transaction
        $txn1 = $order->transactions->first();
        Finance::settle($txn1);

        $order->refresh();
        expect($order->status)->toBeInstanceOf(Pending::class);

        // Now settle the second — Order should complete
        Finance::settle($txn2);

        $order->refresh();
        expect($order->status)->toBeInstanceOf(Completed::class);
    });

    it('does not transition a non-Pending Order', function () {
        ['order' => $order] = createCommittedOrder($this);

        // Comp the Order first (moves to Comped, cancels the pending transaction)
        $order->status->transitionTo(\CorvMC\Finance\States\OrderState\Comped::class);

        // Create a new transaction directly and settle it — should not re-complete
        $txn = Transaction::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'currency' => 'cash',
            'amount' => 3000,
            'type' => 'payment',
            'metadata' => [],
        ]);

        Finance::settle($txn);

        $order->refresh();
        // Should still be Comped, not Completed
        expect($order->status)->toBeInstanceOf(\CorvMC\Finance\States\OrderState\Comped::class);
    });
});
