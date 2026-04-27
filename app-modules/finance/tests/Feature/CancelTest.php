<?php

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Events\OrderCancelled;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\Models\Transaction;
use CorvMC\Finance\States\OrderState\Cancelled;
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
function createCommittedOrderForCancel(object $test, int $amount = 3000): array
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
// Finance::cancel() — happy path
// =========================================================================

describe('Finance::cancel()', function () {
    it('transitions a Pending Order to Cancelled', function () {
        ['order' => $order] = createCommittedOrderForCancel($this);

        $cancelled = Finance::cancel($order);

        expect($cancelled->status)->toBeInstanceOf(Cancelled::class);
    });

    it('cancels Pending Transactions', function () {
        ['order' => $order] = createCommittedOrderForCancel($this);

        $cancelled = Finance::cancel($order);

        $cancelled->transactions->each(function ($txn) {
            expect($txn->status)->toBeInstanceOf(TransactionCancelled::class);
            expect($txn->cancelled_at)->not->toBeNull();
        });
    });

    it('fires OrderCancelled event', function () {
        \Illuminate\Support\Facades\Event::fake([OrderCancelled::class]);

        ['order' => $order] = createCommittedOrderForCancel($this);

        Finance::cancel($order);

        \Illuminate\Support\Facades\Event::assertDispatched(OrderCancelled::class);
    });
});

// =========================================================================
// Credit reversal
// =========================================================================

describe('Finance::cancel() credit reversal', function () {
    it('reverses credit deductions when Order is cancelled', function () {
        // 3 blocks available; 2-hour reservation needs 4 blocks (2 blocks/hour)
        // → 3 consumed, discount = $22.50, net = $7.50
        $this->user->addCredit(
            amount: 3,
            creditType: CreditType::FreeHours,
            source: 'test',
            description: 'Test credit',
        );

        $balanceBefore = Finance::balance($this->user, 'free_hours');
        expect($balanceBefore)->toBe(3);

        // Commit deducts 3 blocks, leaves $7.50 cash
        ['order' => $order] = createCommittedOrderForCancel($this, 750);

        $balanceAfterCommit = Finance::balance($this->user, 'free_hours');
        expect($balanceAfterCommit)->toBe(0);

        // Cancel reverses the deduction
        Finance::cancel($order);

        $balanceAfterCancel = Finance::balance($this->user, 'free_hours');
        expect($balanceAfterCancel)->toBe(3);
    });

    it('does not reverse credits when there are no discounts', function () {
        ['order' => $order] = createCommittedOrderForCancel($this);

        // No credits to reverse — just verify no errors
        Finance::cancel($order);

        expect($order->fresh()->status)->toBeInstanceOf(Cancelled::class);
    });
});

// =========================================================================
// Validation
// =========================================================================

describe('Finance::cancel() validation', function () {
    it('throws when Order is not Pending', function () {
        ['order' => $order] = createCommittedOrderForCancel($this);

        Finance::cancel($order);

        expect(fn () => Finance::cancel($order->fresh()))
            ->toThrow(\RuntimeException::class, 'expected Pending');
    });

    it('throws when Order is Completed', function () {
        ['order' => $order] = createCommittedOrderForCancel($this);

        $txn = $order->transactions->first();
        Finance::settle($txn);

        expect(fn () => Finance::cancel($order->fresh()))
            ->toThrow(\RuntimeException::class, 'expected Pending');
    });
});
