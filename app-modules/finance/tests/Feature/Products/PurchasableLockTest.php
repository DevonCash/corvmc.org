<?php

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Finance\Exceptions\PurchasableLockedException;
use CorvMC\Finance\Models\LineItem;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\States\OrderState\Cancelled;
use CorvMC\Finance\States\OrderState\Completed;
use CorvMC\Finance\States\OrderState\Comped;
use CorvMC\Finance\States\OrderState\Refunded;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->user = User::factory()->create();

    // Deterministic future time within business hours (10am–12pm tomorrow)
    // so watson/validating rules (WithinBusinessHours, NoReservationOverlap) always pass.
    $this->slotCounter = 0;
});

/**
 * Build factory overrides for a reservation slot guaranteed to be
 * in the future, within business hours, and non-overlapping.
 * Each call advances the slot by 3 hours.
 */
function reservationAttrs(object $test): array
{
    $slot = $test->slotCounter++;
    $start = Carbon::tomorrow()->setTime(10, 0)->addHours($slot * 3);

    return [
        'reserved_at' => $start,
        'reserved_until' => $start->copy()->addHours(2),
    ];
}

/**
 * Helper: create an Order with a LineItem pointing at the given model.
 */
function createOrderFor(Model $model, string $productType, int $userId, ?string $status = null): Order
{
    $order = Order::create([
        'user_id' => $userId,
        'total_amount' => 3000,
        'status' => $status,
    ]);

    LineItem::create([
        'order_id' => $order->id,
        'product_type' => $productType,
        'product_id' => $model->getKey(),
        'description' => 'Test item',
        'unit' => 'hour',
        'unit_price' => 1500,
        'quantity' => 2,
        'amount' => 3000,
    ]);

    return $order;
}

// =========================================================================
// Lock prevents modification
// =========================================================================

describe('Purchasable lock', function () {
    it('prevents updating a model with a Pending Order', function () {
        $reservation = RehearsalReservation::factory()->create(array_merge([
            'reservable_id' => $this->user->id,
            'reservable_type' => 'user',
        ], reservationAttrs($this)));

        createOrderFor($reservation, 'rehearsal_time', $this->user->id);

        $reservation->notes = 'Changed notes';
        // Use forceSave() to bypass watson/validating so the purchasable lock observer is what fires
        $reservation->forceSave();
    })->throws(PurchasableLockedException::class);

    it('prevents updating a model with a Completed Order', function () {
        $reservation = RehearsalReservation::factory()->create(array_merge([
            'reservable_id' => $this->user->id,
            'reservable_type' => 'user',
        ], reservationAttrs($this)));

        createOrderFor($reservation, 'rehearsal_time', $this->user->id, Completed::getMorphClass());

        $reservation->notes = 'Changed notes';
        $reservation->forceSave();
    })->throws(PurchasableLockedException::class);

    it('prevents updating a model with a Comped Order', function () {
        $reservation = RehearsalReservation::factory()->create(array_merge([
            'reservable_id' => $this->user->id,
            'reservable_type' => 'user',
        ], reservationAttrs($this)));

        createOrderFor($reservation, 'rehearsal_time', $this->user->id, Comped::getMorphClass());

        $reservation->notes = 'Changed notes';
        $reservation->forceSave();
    })->throws(PurchasableLockedException::class);
});

// =========================================================================
// Lock does NOT prevent modification for terminal Orders
// =========================================================================

describe('Purchasable lock allows modification when Order is terminal', function () {
    it('allows updating when Order is Cancelled', function () {
        $reservation = RehearsalReservation::factory()->create(array_merge([
            'reservable_id' => $this->user->id,
            'reservable_type' => 'user',
        ], reservationAttrs($this)));

        createOrderFor($reservation, 'rehearsal_time', $this->user->id, Cancelled::getMorphClass());

        $reservation->notes = 'Changed notes after cancellation';
        $reservation->forceSave();

        expect($reservation->fresh()->notes)->toBe('Changed notes after cancellation');
    });

    it('allows updating when Order is Refunded', function () {
        $reservation = RehearsalReservation::factory()->create(array_merge([
            'reservable_id' => $this->user->id,
            'reservable_type' => 'user',
        ], reservationAttrs($this)));

        createOrderFor($reservation, 'rehearsal_time', $this->user->id, Refunded::getMorphClass());

        $reservation->notes = 'Changed notes after refund';
        $reservation->forceSave();

        expect($reservation->fresh()->notes)->toBe('Changed notes after refund');
    });
});

// =========================================================================
// Lock does not affect unrelated models
// =========================================================================

describe('Purchasable lock scope', function () {
    it('allows updating a model with no Order', function () {
        $reservation = RehearsalReservation::factory()->create(array_merge([
            'reservable_id' => $this->user->id,
            'reservable_type' => 'user',
        ], reservationAttrs($this)));

        // No Order created — should be freely editable
        $reservation->notes = 'Updated freely';
        $reservation->forceSave();

        expect($reservation->fresh()->notes)->toBe('Updated freely');
    });

    it('does not lock a different model instance', function () {
        $lockedReservation = RehearsalReservation::factory()->create(array_merge([
            'reservable_id' => $this->user->id,
            'reservable_type' => 'user',
        ], reservationAttrs($this)));

        $freeReservation = RehearsalReservation::factory()->create(array_merge([
            'reservable_id' => $this->user->id,
            'reservable_type' => 'user',
        ], reservationAttrs($this)));

        createOrderFor($lockedReservation, 'rehearsal_time', $this->user->id);

        // Different instance should be freely editable
        $freeReservation->notes = 'I am free';
        $freeReservation->forceSave();

        expect($freeReservation->fresh()->notes)->toBe('I am free');
    });

    it('allows creating new models even when others are locked', function () {
        $existing = RehearsalReservation::factory()->create(array_merge([
            'reservable_id' => $this->user->id,
            'reservable_type' => 'user',
        ], reservationAttrs($this)));

        createOrderFor($existing, 'rehearsal_time', $this->user->id);

        // Creating a new reservation should work fine — separate slot avoids overlap
        $new = RehearsalReservation::factory()->create(array_merge([
            'reservable_id' => $this->user->id,
            'reservable_type' => 'user',
        ], reservationAttrs($this)));

        expect($new->exists)->toBeTrue();
    });
});

// =========================================================================
// findActiveOrder helper
// =========================================================================

describe('findActiveOrder', function () {
    it('returns the active Order for a locked model', function () {
        $reservation = RehearsalReservation::factory()->create(array_merge([
            'reservable_id' => $this->user->id,
            'reservable_type' => 'user',
        ], reservationAttrs($this)));

        $order = createOrderFor($reservation, 'rehearsal_time', $this->user->id);

        $manager = app(\CorvMC\Finance\FinanceManager::class);
        $found = $manager->findActiveOrder($reservation);

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($order->id);
    });

    it('returns null when no active Order exists', function () {
        $reservation = RehearsalReservation::factory()->create(array_merge([
            'reservable_id' => $this->user->id,
            'reservable_type' => 'user',
        ], reservationAttrs($this)));

        $manager = app(\CorvMC\Finance\FinanceManager::class);

        expect($manager->findActiveOrder($reservation))->toBeNull();
    });

    it('returns null when Order is terminal', function () {
        $reservation = RehearsalReservation::factory()->create(array_merge([
            'reservable_id' => $this->user->id,
            'reservable_type' => 'user',
        ], reservationAttrs($this)));

        createOrderFor($reservation, 'rehearsal_time', $this->user->id, Cancelled::getMorphClass());

        $manager = app(\CorvMC\Finance\FinanceManager::class);

        expect($manager->findActiveOrder($reservation))->toBeNull();
    });
});
