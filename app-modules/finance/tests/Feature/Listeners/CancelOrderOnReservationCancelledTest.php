<?php

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\States\OrderState\Cancelled as OrderCancelled;
use CorvMC\Finance\States\OrderState\Completed as OrderCompleted;
use CorvMC\Finance\States\OrderState\Refunded as OrderRefunded;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->user = User::factory()->create();
});

/**
 * Create a reservation with a committed order in the given state.
 *
 * @param  'pending'|'completed'  $orderState
 */
function createReservationForCancelTest(object $test, ?Carbon $startTime = null, string $orderState = 'pending'): array
{
    $startTime ??= Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
    $endTime = $startTime->copy()->addHours(2);

    $reservation = RehearsalReservation::create([
        'reservable_type' => User::class,
        'reservable_id' => $test->user->id,
        'reserved_at' => $startTime,
        'reserved_until' => $endTime,
        'status' => RehearsalReservation::determineStatusForDate($startTime),
    ]);

    $order = Order::create([
        'user_id' => $test->user->id,
        'total_amount' => 0,
    ]);

    $lineItems = Finance::price([$reservation], $test->user);
    foreach ($lineItems as $lineItem) {
        $lineItem->order_id = $order->id;
        $lineItem->save();
    }
    $netTotal = $lineItems->sum('amount');
    $order->update(['total_amount' => $netTotal]);

    if ($netTotal > 0) {
        $committed = Finance::commit($order->fresh(), ['cash' => $netTotal]);
    } else {
        $committed = Finance::commit($order->fresh(), []);
    }

    if ($orderState === 'completed' && $committed->status instanceof \CorvMC\Finance\States\OrderState\Pending) {
        $txn = $committed->transactions->first();
        Finance::settle($txn);
        $committed = $committed->fresh(['lineItems', 'transactions']);
    }

    return ['reservation' => $reservation, 'order' => $committed];
}

describe('CancelOrderOnReservationCancelled listener', function () {
    it('cancels a pending order when reservation is cancelled', function () {
        ['reservation' => $reservation, 'order' => $order] = createReservationForCancelTest($this);

        expect($order->status)->toBeInstanceOf(\CorvMC\Finance\States\OrderState\Pending::class);

        $reservation->cancel('Changed plans');

        expect($order->fresh()->status)->toBeInstanceOf(OrderCancelled::class);
    });

    it('refunds a completed order when a future reservation is cancelled', function () {
        ['reservation' => $reservation, 'order' => $order] = createReservationForCancelTest(
            $this,
            startTime: Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0),
            orderState: 'completed',
        );

        expect($order->status)->toBeInstanceOf(OrderCompleted::class);
        expect($reservation->reserved_at->isFuture())->toBeTrue();

        $reservation->cancel('Changed plans');

        expect($order->fresh()->status)->toBeInstanceOf(OrderRefunded::class);
    });

    it('does not refund a completed order when a past reservation is cancelled', function () {
        // Create a reservation in the past using forceSave to bypass validation
        $startTime = Carbon::now()->subDays(2)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = RehearsalReservation::factory()->make([
            'reservable_type' => 'user',
            'reservable_id' => $this->user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => \CorvMC\SpaceManagement\States\ReservationState\Confirmed::class,
        ]);
        $reservation->forceSave();
        $reservation = $reservation->fresh();

        // Build and commit an order, then settle it
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 0,
        ]);

        $lineItems = Finance::price([$reservation], $this->user);
        foreach ($lineItems as $lineItem) {
            $lineItem->order_id = $order->id;
            $lineItem->save();
        }
        $netTotal = $lineItems->sum('amount');
        $order->update(['total_amount' => $netTotal]);

        $committed = Finance::commit($order->fresh(), ['cash' => $netTotal]);
        Finance::settle($committed->transactions->first());
        $committed = $committed->fresh();

        expect($committed->status)->toBeInstanceOf(OrderCompleted::class);
        expect($reservation->reserved_at->isPast())->toBeTrue();

        // Dispatch the event directly (bypasses watson validation on past dates)
        \CorvMC\SpaceManagement\Events\ReservationCancelled::dispatch($reservation);

        // Order should NOT be auto-refunded since the reservation already started
        expect($committed->fresh()->status)->toBeInstanceOf(OrderCompleted::class);
    });

    it('restores credits when refunding a cancelled future reservation', function () {
        $this->user = User::factory()->sustainingMember()->create();
        $this->user->addCredit(
            amount: 8,
            creditType: CreditType::FreeHours,
            source: 'test_allocation',
            description: 'Test allocation',
        );

        ['reservation' => $reservation, 'order' => $order] = createReservationForCancelTest(
            $this,
            startTime: Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0),
            orderState: 'completed',
        );

        // Fully covered by credits → order auto-completed at commit
        expect($order->status)->toBeInstanceOf(OrderCompleted::class);

        $balanceAfterCommit = $this->user->fresh()->getCreditBalance(CreditType::FreeHours);

        $reservation->cancel('No longer needed');

        expect($order->fresh()->status)->toBeInstanceOf(OrderRefunded::class);

        $balanceAfterCancel = $this->user->fresh()->getCreditBalance(CreditType::FreeHours);
        expect($balanceAfterCancel)->toBeGreaterThan($balanceAfterCommit);
        expect($balanceAfterCancel)->toBe(8); // fully restored
    });
});
