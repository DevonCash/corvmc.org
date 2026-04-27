<?php

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\States\OrderState\Cancelled as OrderCancelled;
use CorvMC\Finance\States\OrderState\Completed as OrderCompleted;
use CorvMC\Finance\States\OrderState\Pending as OrderPending;
use CorvMC\Finance\States\OrderState\Refunded as OrderRefunded;
use CorvMC\SpaceManagement\Facades\ReservationService;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\States\ReservationState\Cancelled;
use CorvMC\SpaceManagement\States\ReservationState\Confirmed;
use CorvMC\SpaceManagement\States\ReservationState\Reserved;
use CorvMC\SpaceManagement\States\ReservationState\Scheduled;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    \Illuminate\Support\Facades\Cache::flush();
});

/**
 * Helper: create a reservation and build a committed Order for it.
 */
function createReservationWithOrder(object $test, User $user, ?string $rail = 'cash', ?Carbon $startTime = null): array
{
    $startTime ??= Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
    $endTime = $startTime->copy()->addHours(2);

    $reservation = RehearsalReservation::create([
        'reservable_type' => User::class,
        'reservable_id' => $user->id,
        'reserved_at' => $startTime,
        'reserved_until' => $endTime,
        'status' => RehearsalReservation::determineStatusForDate($startTime),
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'total_amount' => 0,
    ]);

    $lineItems = Finance::price([$reservation], $user);
    foreach ($lineItems as $lineItem) {
        $lineItem->order_id = $order->id;
        $lineItem->save();
    }
    $order->update(['total_amount' => $lineItems->sum('amount')]);

    $totalAmount = $lineItems->sum('amount');

    if ($totalAmount > 0 && $rail) {
        $committed = Finance::commit($order->fresh(), [$rail => $totalAmount]);
    } else {
        $committed = Finance::commit($order->fresh(), []);
    }

    return ['reservation' => $reservation, 'order' => $committed, 'lineItems' => $lineItems];
}

describe('Reservation Workflow: Create Single Reservation', function () {
    it('creates a reservation for a regular user with correct cost calculation', function () {
        $user = User::factory()->create();
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = RehearsalReservation::create([
            'reservable_type' => User::class,
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => RehearsalReservation::determineStatusForDate($startTime),
        ]);

        expect($reservation)->toBeInstanceOf(RehearsalReservation::class);
        expect($reservation->reservable_id)->toBe($user->id);
        expect((float) $reservation->duration)->toBe(2.0);
        expect($reservation->status)->toBeInstanceOf(Scheduled::class);

        // Verify pricing via Finance::price()
        $lineItems = Finance::price([$reservation]);
        expect($lineItems)->toHaveCount(1);
        expect($lineItems->first()->amount)->toBe(3000); // $15/hour × 2 hours = $30
    });

    it('applies free hours discount for sustaining member with credits', function () {
        $user = User::factory()->sustainingMember()->create();
        $user->addCredit(
            amount: 8,
            creditType: CreditType::FreeHours,
            source: 'test_allocation',
            description: 'Test allocation',
        );

        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = RehearsalReservation::create([
            'reservable_type' => User::class,
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => RehearsalReservation::determineStatusForDate($startTime),
        ]);

        // Price with user context to see discount applied
        $lineItems = Finance::price([$reservation], $user);

        // Should have base item + discount item
        $baseItem = $lineItems->first(fn ($item) => $item->amount > 0);
        $discountItem = $lineItems->first(fn ($item) => $item->amount < 0);

        expect($baseItem->amount)->toBe(3000); // $15/hour × 2 hours
        expect($discountItem)->not->toBeNull();
        expect($discountItem->product_type)->toBe('free_hours_discount');

        // 2 hours × 2 blocks/hour = 4 blocks consumed × $7.50 = $30 (fully covered)
        expect($discountItem->amount)->toBe(-3000);
        expect($lineItems->sum('amount'))->toBe(0); // $30 base - $30 discount
    });

    it('auto-confirms reservations less than 3 days away', function () {
        $user = User::factory()->create();
        $startTime = Carbon::now()->addDays(1)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = RehearsalReservation::create([
            'reservable_type' => User::class,
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => RehearsalReservation::determineStatusForDate($startTime),
        ]);

        expect($reservation->status)->toBeInstanceOf(Confirmed::class);
    });
});

describe('Reservation Workflow: Confirm Reservation', function () {
    it('confirms a scheduled reservation', function () {
        $user = User::factory()->admin()->create();
        $startTime = Carbon::now()->addDays(4)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = RehearsalReservation::create([
            'reservable_type' => User::class,
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => RehearsalReservation::determineStatusForDate($startTime),
        ]);
        expect($reservation->status)->toBeInstanceOf(Scheduled::class);

        $this->travel(2)->days();

        $confirmedReservation = $reservation->confirm();

        expect($confirmedReservation->status)->toBeInstanceOf(Confirmed::class);
    });

    it('deducts credits when committing an order for a reservation', function () {
        $user = User::factory()->sustainingMember()->create();
        $user->addCredit(
            amount: 8,
            creditType: CreditType::FreeHours,
            source: 'test_allocation',
            description: 'Test allocation',
        );

        $initialBalance = $user->getCreditBalance(CreditType::FreeHours);
        expect($initialBalance)->toBe(8);

        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = RehearsalReservation::create([
            'reservable_type' => User::class,
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => RehearsalReservation::determineStatusForDate($startTime),
        ]);

        // Credits are not deducted until Finance::commit()
        expect($user->fresh()->getCreditBalance(CreditType::FreeHours))->toBe(8);

        // Create and commit an Order (this is when credits get deducted)
        $order = Order::create(['user_id' => $user->id, 'total_amount' => 0]);
        $lineItems = Finance::price([$reservation], $user);
        foreach ($lineItems as $lineItem) {
            $lineItem->order_id = $order->id;
            $lineItem->save();
        }
        $netTotal = $lineItems->sum('amount');
        $order->update(['total_amount' => $netTotal]);

        // Net total is $0 ($30 base - $30 discount), no cash rail needed
        expect($netTotal)->toBe(0);
        Finance::commit($order->fresh(), []);

        // Credits should now be deducted (4 blocks consumed: 2h × 2 blocks/h)
        $newBalance = $user->fresh()->getCreditBalance(CreditType::FreeHours);
        expect($newBalance)->toBeLessThan($initialBalance);
        expect($newBalance)->toBe(4); // 8 - 4 blocks consumed
    });
});

describe('Reservation Workflow: Availability Check', function () {
    it('returns empty collection when time slot is available', function () {
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $conflicts = ReservationService::getConflicts($startTime, $endTime);

        expect($conflicts)->toBeEmpty();
    });

    it('returns false when time slot conflicts with existing reservation', function () {
        $user = User::factory()->create();
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        RehearsalReservation::create([
            'reservable_type' => User::class,
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => RehearsalReservation::determineStatusForDate($startTime),
        ]);

        $overlapStart = $startTime->copy()->addMinutes(30);
        $overlapEnd = $overlapStart->copy()->addHours(2);

        $conflicts = ReservationService::getConflicts($overlapStart, $overlapEnd);

        expect($conflicts)->not->toBeEmpty();
    });

    it('excludes a specific reservation when checking availability', function () {
        $user = User::factory()->create();
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = RehearsalReservation::create([
            'reservable_type' => User::class,
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => RehearsalReservation::determineStatusForDate($startTime),
        ]);

        $conflicts = ReservationService::getConflicts($startTime, $endTime, $reservation->id);

        expect($conflicts)->toBeEmpty();
    });
});

describe('Reservation Workflow: Cancel Reservation', function () {
    it('cancels a reservation and updates status', function () {
        $user = User::factory()->create();
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = RehearsalReservation::create([
            'reservable_type' => User::class,
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => RehearsalReservation::determineStatusForDate($startTime),
        ]);

        $cancelledReservation = $reservation->cancel('Test cancellation');

        expect($cancelledReservation->status)->toBeInstanceOf(Cancelled::class);
        expect($cancelledReservation->cancellation_reason)->toBe('Test cancellation');
    });

    it('refunds credits when cancelling a fully-covered reservation', function () {
        $user = User::factory()->sustainingMember()->create();
        $user->addCredit(
            amount: 8,
            creditType: CreditType::FreeHours,
            source: 'test_allocation',
            description: 'Test allocation',
        );

        ['reservation' => $reservation, 'order' => $order] = createReservationWithOrder($this, $user);

        // Fully covered by credits → order auto-completed at commit
        expect($order->status)->toBeInstanceOf(OrderCompleted::class);

        $balanceAfterCommit = $user->fresh()->getCreditBalance(CreditType::FreeHours);

        // Refund the order first (unlocks the purchasable), then cancel the reservation
        Finance::refund($order);
        $reservation->cancel('Changed plans');

        expect($reservation->fresh()->status)->toBeInstanceOf(Cancelled::class);
        $balanceAfterRefund = $user->fresh()->getCreditBalance(CreditType::FreeHours);
        expect($balanceAfterRefund)->toBeGreaterThan($balanceAfterCommit);
        expect($balanceAfterRefund)->toBe(8); // fully restored
    });

    it('frees up the time slot after cancellation', function () {
        $user = User::factory()->create();
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = RehearsalReservation::create([
            'reservable_type' => User::class,
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => RehearsalReservation::determineStatusForDate($startTime),
        ]);

        expect(ReservationService::getConflicts($startTime, $endTime))->not->toBeEmpty();

        $reservation->cancel();

        expect(ReservationService::getConflicts($startTime, $endTime))->toBeEmpty();
    });

    it('cancels Order when cancelling an unpaid reservation', function () {
        $user = User::factory()->create();

        ['order' => $order] = createReservationWithOrder($this, $user);
        expect($order->status)->toBeInstanceOf(OrderPending::class);

        Finance::cancel($order);

        expect($order->fresh()->status)->toBeInstanceOf(OrderCancelled::class);
    });

    it('cancelling a credit-covered reservation triggers refund and restores credits', function () {
        $user = User::factory()->sustainingMember()->create();
        $user->addCredit(
            amount: 8,
            creditType: CreditType::FreeHours,
            source: 'test_allocation',
            description: 'Test allocation',
        );

        ['reservation' => $reservation, 'order' => $order] = createReservationWithOrder($this, $user);

        // Fully covered by credits → order auto-completed at commit
        expect($order->status)->toBeInstanceOf(OrderCompleted::class);

        $balanceBeforeRefund = $user->fresh()->getCreditBalance(CreditType::FreeHours);

        // Refund the order first (unlocks the purchasable), then cancel the reservation
        Finance::refund($order);
        $reservation->cancel('No longer needed');

        expect($order->fresh()->status)->toBeInstanceOf(OrderRefunded::class);
        expect($reservation->fresh()->status)->toBeInstanceOf(Cancelled::class);

        // Credits should be restored
        $balanceAfterRefund = $user->fresh()->getCreditBalance(CreditType::FreeHours);
        expect($balanceAfterRefund)->toBeGreaterThan($balanceBeforeRefund);
    });

    it('refunds Order when cancelling a paid reservation', function () {
        $user = User::factory()->create();

        ['order' => $order] = createReservationWithOrder($this, $user, 'cash');

        // Settle the cash transaction to mark it as paid
        $txn = $order->transactions->first();
        Finance::settle($txn);

        $order->refresh();
        expect($order->status)->toBeInstanceOf(OrderCompleted::class);

        // Refund the completed order
        Finance::refund($order);

        expect($order->fresh()->status)->toBeInstanceOf(OrderRefunded::class);
    });
});
