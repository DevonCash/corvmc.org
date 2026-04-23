<?php

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Events\OrderSettled;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\Models\Transaction;
use CorvMC\Finance\States\OrderState\Comped;
use CorvMC\Finance\States\OrderState\Completed;
use CorvMC\Finance\States\OrderState\Pending;
use CorvMC\Finance\States\TransactionState\Cancelled as TransactionCancelled;
use CorvMC\SpaceManagement\Models\RehearsalReservation;

beforeEach(function () {
    $this->user = User::factory()->create();
});

/**
 * Create a committed Order with a Pending cash Transaction.
 */
function createCommittedOrderForComp(object $test, int $amount = 3000): array
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
// Finance::comp() — happy path
// =========================================================================

describe('Finance::comp()', function () {
    it('transitions a Pending Order to Comped', function () {
        ['order' => $order] = createCommittedOrderForComp($this);

        $comped = Finance::comp($order);

        expect($comped->status)->toBeInstanceOf(Comped::class);
        expect($comped->settled_at)->not->toBeNull();
    });

    it('cancels Pending Transactions', function () {
        ['order' => $order] = createCommittedOrderForComp($this);

        $comped = Finance::comp($order);

        $comped->transactions->each(function ($txn) {
            expect($txn->status)->toBeInstanceOf(TransactionCancelled::class);
            expect($txn->cancelled_at)->not->toBeNull();
        });
    });

    it('fires OrderSettled event', function () {
        \Illuminate\Support\Facades\Event::fake([OrderSettled::class]);

        ['order' => $order] = createCommittedOrderForComp($this);

        Finance::comp($order);

        \Illuminate\Support\Facades\Event::assertDispatched(OrderSettled::class);
    });

    it('does not reverse credit deductions', function () {
        $this->user->addCredit(
            amount: 5,
            creditType: CreditType::FreeHours,
            source: 'test',
            description: 'Test credit',
        );

        ['order' => $order] = createCommittedOrderForComp($this, 1500);

        // Credits were deducted at commit time
        $balanceAfterCommit = Finance::balance($this->user, 'free_hours');

        Finance::comp($order);

        // Balance should be unchanged after comp
        $balanceAfterComp = Finance::balance($this->user, 'free_hours');
        expect($balanceAfterComp)->toBe($balanceAfterCommit);
    });
});

// =========================================================================
// Finance::comp() — validation
// =========================================================================

describe('Finance::comp() validation', function () {
    it('throws when Order is not Pending', function () {
        ['order' => $order] = createCommittedOrderForComp($this);

        Finance::comp($order);

        expect(fn () => Finance::comp($order->fresh()))
            ->toThrow(\RuntimeException::class, 'expected Pending');
    });

    it('throws when Order is already Completed', function () {
        ['order' => $order] = createCommittedOrderForComp($this);

        // Settle the transaction to complete the order
        $txn = $order->transactions->first();
        Finance::settle($txn);

        expect(fn () => Finance::comp($order->fresh()))
            ->toThrow(\RuntimeException::class, 'expected Pending');
    });
});
