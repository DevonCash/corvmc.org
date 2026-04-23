<?php

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Events\OrderRefunded;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\Models\Transaction;
use CorvMC\Finance\States\OrderState\Completed;
use CorvMC\Finance\States\OrderState\Comped;
use CorvMC\Finance\States\OrderState\Pending;
use CorvMC\Finance\States\OrderState\Refunded;
use CorvMC\Finance\States\TransactionState\Cleared;
use CorvMC\Finance\States\TransactionState\Pending as TransactionPending;
use CorvMC\SpaceManagement\Models\RehearsalReservation;

beforeEach(function () {
    $this->user = User::factory()->create();
});

/**
 * Create a committed and settled (Completed) Order with a Cleared cash Transaction.
 */
function createCompletedCashOrder(object $test, int $amount = 3000): array
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

    // Settle the cash Transaction to move Order to Completed
    $txn = $committed->transactions->first();
    Finance::settle($txn);

    return ['order' => $committed->fresh(['lineItems', 'transactions']), 'reservation' => $reservation];
}

// =========================================================================
// Finance::refund() — cash refund
// =========================================================================

describe('Finance::refund() with cash', function () {
    it('transitions a Completed Order to Refunded', function () {
        ['order' => $order] = createCompletedCashOrder($this);

        expect($order->status)->toBeInstanceOf(Completed::class);

        $refunded = Finance::refund($order);

        expect($refunded->status)->toBeInstanceOf(Refunded::class);
    });

    it('creates a compensating refund Transaction', function () {
        ['order' => $order] = createCompletedCashOrder($this);

        $refunded = Finance::refund($order);

        $refundTxns = $refunded->transactions->where('type', 'refund');
        expect($refundTxns)->toHaveCount(1);

        $refundTxn = $refundTxns->first();
        expect($refundTxn->currency)->toBe('cash');
        expect($refundTxn->amount)->toBe(3000); // positive = money returning
        expect($refundTxn->status)->toBeInstanceOf(TransactionPending::class);
    });

    it('links refund Transaction to original payment via metadata', function () {
        ['order' => $order] = createCompletedCashOrder($this);

        $paymentTxn = $order->transactions->where('type', 'payment')->first();

        $refunded = Finance::refund($order);

        $refundTxn = $refunded->transactions->where('type', 'refund')->first();
        expect($refundTxn->metadata['original_transaction_id'])->toBe($paymentTxn->id);
    });

    it('fires OrderRefunded event', function () {
        \Illuminate\Support\Facades\Event::fake([OrderRefunded::class]);

        ['order' => $order] = createCompletedCashOrder($this);

        Finance::refund($order);

        \Illuminate\Support\Facades\Event::assertDispatched(OrderRefunded::class);
    });
});

/**
 * Create a committed and comped Order.
 */
function createCompedOrder(object $test): array
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

    $committed = Finance::commit($order->fresh(), ['cash' => 3000]);
    $comped = Finance::comp($committed);

    return ['order' => $comped->fresh(['lineItems', 'transactions']), 'reservation' => $reservation];
}

// =========================================================================
// Finance::refund() — comped order
// =========================================================================

describe('Finance::refund() for comped order', function () {
    it('transitions a Comped Order to Refunded', function () {
        ['order' => $order] = createCompedOrder($this);

        expect($order->status)->toBeInstanceOf(Comped::class);

        $refunded = Finance::refund($order);

        expect($refunded->status)->toBeInstanceOf(Refunded::class);
    });

    it('creates no refund Transactions for comped order (no Cleared payments)', function () {
        ['order' => $order] = createCompedOrder($this);

        $refunded = Finance::refund($order);

        $refundTxns = $refunded->transactions->where('type', 'refund');
        expect($refundTxns)->toHaveCount(0);
    });
});

// =========================================================================
// Credit reversal on refund
// =========================================================================

describe('Finance::refund() credit reversal', function () {
    it('reverses credit deductions when Order is refunded', function () {
        $this->user->addCredit(
            amount: 5,
            creditType: CreditType::FreeHours,
            source: 'test',
            description: 'Test credit',
        );

        expect(Finance::balance($this->user, 'free_hours'))->toBe(5);

        // Commit deducts 2 blocks
        ['order' => $order] = createCompletedCashOrder($this, 1500);

        expect(Finance::balance($this->user, 'free_hours'))->toBe(3);

        // Refund reverses the deduction
        Finance::refund($order);

        expect(Finance::balance($this->user, 'free_hours'))->toBe(5);
    });
});

// =========================================================================
// Validation
// =========================================================================

describe('Finance::refund() validation', function () {
    it('throws when Order is Pending', function () {
        // Comped orders go through commit first — use a simpler path:
        // create a committed (but unsettled) order directly
        ['order' => $order] = createCompedOrder($this);

        // Transition back isn't possible, so just test with a fresh committed order
        // by using createCompletedCashOrder's internals without the settle step
        $reservation = RehearsalReservation::factory()->create([
            'reservable_id' => $this->user->id,
            'reservable_type' => 'user',
            'reserved_at' => Carbon::tomorrow()->setTime(14, 0),
            'reserved_until' => Carbon::tomorrow()->setTime(16, 0),
        ]);

        $pendingOrder = Order::create(['user_id' => $this->user->id, 'total_amount' => 0]);
        $lineItems = Finance::price([$reservation]);
        foreach ($lineItems as $li) {
            $li->order_id = $pendingOrder->id;
            $li->save();
        }
        $pendingOrder->update(['total_amount' => $lineItems->sum('amount')]);
        $committed = Finance::commit($pendingOrder->fresh(), ['cash' => 3000]);

        expect(fn () => Finance::refund($committed))
            ->toThrow(\RuntimeException::class, 'expected Completed or Comped');
    });

    it('throws when Order is already Refunded', function () {
        ['order' => $order] = createCompletedCashOrder($this);

        Finance::refund($order);

        expect(fn () => Finance::refund($order->fresh()))
            ->toThrow(\RuntimeException::class, 'expected Completed or Comped');
    });

    it('throws when Order is Cancelled', function () {
        ['order' => $order] = createCompedOrder($this);

        // Comped → can't cancel. Use a fresh committed order instead.
        $reservation = RehearsalReservation::factory()->create([
            'reservable_id' => $this->user->id,
            'reservable_type' => 'user',
            'reserved_at' => Carbon::tomorrow()->setTime(14, 0),
            'reserved_until' => Carbon::tomorrow()->setTime(16, 0),
        ]);

        $cancelOrder = Order::create(['user_id' => $this->user->id, 'total_amount' => 0]);
        $lineItems = Finance::price([$reservation]);
        foreach ($lineItems as $li) {
            $li->order_id = $cancelOrder->id;
            $li->save();
        }
        $cancelOrder->update(['total_amount' => $lineItems->sum('amount')]);
        $committed = Finance::commit($cancelOrder->fresh(), ['cash' => 3000]);
        Finance::cancel($committed);

        expect(fn () => Finance::refund($committed->fresh()))
            ->toThrow(\RuntimeException::class, 'expected Completed or Comped');
    });
});
