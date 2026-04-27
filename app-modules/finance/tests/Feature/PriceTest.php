<?php

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\LineItem;
use CorvMC\SpaceManagement\Models\RehearsalReservation;

beforeEach(function () {
    $this->user = User::factory()->create();
});

/**
 * Create a RehearsalReservation with deterministic times within business hours.
 */
function makeReservation(object $test, float $hours = 2.0, ?array $overrides = []): RehearsalReservation
{
    return RehearsalReservation::factory()->create(array_merge([
        'reservable_id' => $test->user->id,
        'reservable_type' => 'user',
        'reserved_at' => Carbon::tomorrow()->setTime(10, 0),
        'reserved_until' => Carbon::tomorrow()->setTime(10, 0)->addMinutes((int) ($hours * 60)),
    ], $overrides));
}

// =========================================================================
// Base LineItems (no user, no discounts)
// =========================================================================

describe('Finance::price() base LineItems', function () {
    it('returns a base LineItem for a single model', function () {
        $reservation = makeReservation($this, 2.0);

        $lineItems = Finance::price([$reservation]);

        expect($lineItems)->toHaveCount(1);

        $item = $lineItems->first();
        expect($item)->toBeInstanceOf(LineItem::class);
        expect($item->exists)->toBeFalse(); // unpersisted
        expect($item->product_type)->toBe('rehearsal_time');
        expect($item->product_id)->toBe($reservation->id);
        expect($item->unit)->toBe('hour');
        expect($item->unit_price)->toBe(1500);
        expect($item->quantity)->toBe('2.00');
        expect($item->amount)->toBe(3000);
    });

    it('returns base LineItems for multiple models', function () {
        $r1 = makeReservation($this, 2.0);
        $r2 = makeReservation($this, 1.5, [
            'reserved_at' => Carbon::tomorrow()->setTime(14, 0),
            'reserved_until' => Carbon::tomorrow()->setTime(15, 30),
        ]);

        $lineItems = Finance::price([$r1, $r2]);

        expect($lineItems)->toHaveCount(2);
        expect($lineItems[0]->amount)->toBe(3000); // 2h × $15
        expect($lineItems[1]->amount)->toBe(2250); // 1.5h × $15
    });

    it('includes description from the Product', function () {
        $reservation = makeReservation($this, 2.0);

        $lineItems = Finance::price([$reservation]);

        expect($lineItems->first()->description)->toContain('Practice Space');
    });

    it('returns empty collection for empty input', function () {
        $lineItems = Finance::price([]);

        expect($lineItems)->toBeEmpty();
    });
});

// =========================================================================
// Discount LineItems (with user and wallet balance)
// =========================================================================

describe('Finance::price() wallet discounts', function () {
    it('emits a discount LineItem when user has wallet balance', function () {
        // Give user 4 blocks of free_hours
        $this->user->addCredit(
            amount: 4,
            creditType: CreditType::FreeHours,
            source: 'test',
            description: 'Test credit',
        );

        $reservation = makeReservation($this, 2.0); // 2 hours = 4 blocks at 2 blocks/hour

        $lineItems = Finance::price([$reservation], $this->user);

        expect($lineItems)->toHaveCount(2);

        $base = $lineItems[0];
        $discount = $lineItems[1];

        expect($base->amount)->toBe(3000);
        expect($discount->product_type)->toBe('free_hours_discount');
        expect($discount->unit)->toBe('discount');
        expect($discount->unit_price)->toBe(-750);
        expect($discount->quantity)->toBe('4.00'); // 4 blocks consumed (2 hours × 2 blocks/hour)
        expect($discount->amount)->toBe(-3000); // 4 × $7.50 = fully covered
        expect($discount->product_id)->toBeNull();
    });

    it('caps discount blocks at what the item needs', function () {
        // User has 10 blocks but reservation is only 2 hours (4 blocks needed)
        $this->user->addCredit(
            amount: 10,
            creditType: CreditType::FreeHours,
            source: 'test',
            description: 'Test credit',
        );

        $reservation = makeReservation($this, 2.0);

        $lineItems = Finance::price([$reservation], $this->user);

        $discount = $lineItems[1];
        expect($discount->quantity)->toBe('4.00'); // capped at 4 blocks (2h × 2 blocks/h), not 10
        expect($discount->amount)->toBe(-3000); // fully covered
    });

    it('caps discount at the base LineItem amount and floors to whole blocks', function () {
        // Override cents_per_unit so raw block discount exceeds the base amount.
        // With cents_per_unit = 2000 and rate = $15/hr:
        //   blocksPerBillableUnit = 1500/2000 = 0.75
        //   maxBlocksForItem = 2 × 0.75 = 1.5
        //   discountCents = (int)(1.5 × 2000) = 3000, equals base → cap at base
        //   actualBlocks = floor(3000 / 2000) = 1, discount = 1 × 2000 = 2000
        config(['finance.wallets.free_hours.cents_per_unit' => 2000]);

        $this->user->addCredit(
            amount: 10,
            creditType: CreditType::FreeHours,
            source: 'test',
            description: 'Test credit',
        );

        $reservation = makeReservation($this, 2.0); // 2 hours = $30

        $lineItems = Finance::price([$reservation], $this->user);

        expect($lineItems)->toHaveCount(2);

        $discount = $lineItems[1];
        // Cap fired: floor(3000 / 2000) = 1 whole block consumed
        expect($discount->quantity)->toBe('1.00');
        expect($discount->unit_price)->toBe(-2000);
        expect($discount->amount)->toBe(-2000); // not -3000 or -4000
    });

    it('applies partial discount when balance is less than billable units', function () {
        // User has 1 block, reservation is 3 hours
        $this->user->addCredit(
            amount: 1,
            creditType: CreditType::FreeHours,
            source: 'test',
            description: 'Test credit',
        );

        $reservation = makeReservation($this, 3.0);

        $lineItems = Finance::price([$reservation], $this->user);

        expect($lineItems)->toHaveCount(2);

        $discount = $lineItems[1];
        expect($discount->quantity)->toBe('1.00'); // only 1 block available
        expect($discount->amount)->toBe(-750);
    });

    it('emits no discount when user has zero balance', function () {
        $reservation = makeReservation($this, 2.0);

        $lineItems = Finance::price([$reservation], $this->user);

        expect($lineItems)->toHaveCount(1); // base only, no discount
    });

    it('emits no discount when product has no eligible wallets', function () {
        // Tickets have no eligible wallets
        $this->user->addCredit(
            amount: 10,
            creditType: CreditType::FreeHours,
            source: 'test',
            description: 'Test credit',
        );

        $ticket = \CorvMC\Events\Models\TicketOrder::factory()->create();

        $lineItems = Finance::price([$ticket], $this->user);

        expect($lineItems)->toHaveCount(1); // base only
    });
});

// =========================================================================
// First-come-first-served across multiple items
// =========================================================================

describe('Finance::price() multi-item wallet sharing', function () {
    it('applies credits first-come-first-served across items', function () {
        // User has 3 blocks. Two reservations of 2 hours each (4 blocks needed each).
        // First gets all 3 remaining blocks, second gets nothing.
        $this->user->addCredit(
            amount: 3,
            creditType: CreditType::FreeHours,
            source: 'test',
            description: 'Test credit',
        );

        $r1 = makeReservation($this, 2.0);
        $r2 = makeReservation($this, 2.0, [
            'reserved_at' => Carbon::tomorrow()->setTime(14, 0),
            'reserved_until' => Carbon::tomorrow()->setTime(16, 0),
        ]);

        $lineItems = Finance::price([$r1, $r2], $this->user);

        // 2 base + 1 discount (second gets nothing) = 3
        expect($lineItems)->toHaveCount(3);

        // First reservation: 3 blocks applied (all available, needs 4)
        $discount1 = $lineItems[2];
        expect($discount1->quantity)->toBe('3.00');
        expect($discount1->amount)->toBe(-2250);
    });

    it('exhausts wallet and skips subsequent items', function () {
        // User has 1 block. Two reservations.
        // First gets 1 block, second gets nothing.
        $this->user->addCredit(
            amount: 1,
            creditType: CreditType::FreeHours,
            source: 'test',
            description: 'Test credit',
        );

        $r1 = makeReservation($this, 2.0);
        $r2 = makeReservation($this, 2.0, [
            'reserved_at' => Carbon::tomorrow()->setTime(14, 0),
            'reserved_until' => Carbon::tomorrow()->setTime(16, 0),
        ]);

        $lineItems = Finance::price([$r1, $r2], $this->user);

        // 2 base + 1 discount = 3
        expect($lineItems)->toHaveCount(3);

        $discount = $lineItems[2];
        expect($discount->quantity)->toBe('1.00');
        expect($discount->amount)->toBe(-750);
    });
});

// =========================================================================
// No user — same as no discounts
// =========================================================================

describe('Finance::price() without user', function () {
    it('returns only base LineItems when no user is provided', function () {
        $reservation = makeReservation($this, 2.0);

        $lineItems = Finance::price([$reservation]);

        expect($lineItems)->toHaveCount(1);
        expect($lineItems->first()->amount)->toBe(3000);
    });
});
