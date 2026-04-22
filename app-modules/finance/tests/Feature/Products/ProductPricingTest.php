<?php

use App\Finance\Products\EquipmentLoanProduct;
use App\Finance\Products\ProcessingFeeProduct;
use App\Finance\Products\RehearsalProduct;
use App\Finance\Products\TicketProduct;
use CorvMC\Finance\Facades\Finance;
use CorvMC\SpaceManagement\Models\RehearsalReservation;

// =========================================================================
// RehearsalProduct pricing
// =========================================================================

describe('RehearsalProduct pricing', function () {
    it('computes billable hours from reservation time span', function () {
        $reservation = RehearsalReservation::factory()->make([
            'reserved_at' => now(),
            'reserved_until' => now()->addHours(2),
        ]);

        $product = Finance::productFor($reservation);

        expect($product->billableUnits)->toBe(2.0);
    });

    it('returns zero billable units when dates are missing', function () {
        $reservation = RehearsalReservation::factory()->make([
            'reserved_at' => null,
            'reserved_until' => null,
        ]);

        $product = Finance::productFor($reservation);

        expect($product->billableUnits)->toBe(0.0);
    });

    it('reads price per unit from config', function () {
        // Default config: 1500 cents ($15/hr)
        expect(RehearsalProduct::getPricePerUnit())->toBe(1500);
    });

    it('computes totalAmount as hours × rate', function () {
        $reservation = RehearsalReservation::factory()->make([
            'reserved_at' => now(),
            'reserved_until' => now()->addHours(2),
        ]);

        $product = Finance::productFor($reservation);

        // 2 hours × 1500 cents = 3000 cents ($30)
        expect($product->totalAmount())->toBe(3000);
    });

    it('returns free_hours as eligible wallet', function () {
        expect(RehearsalProduct::getEligibleWallets())->toBe(['free_hours']);
    });

    it('has unit "hour"', function () {
        expect(RehearsalProduct::getUnit())->toBe('hour');
    });

    it('generates a description with hours and date', function () {
        $reservation = RehearsalReservation::factory()->make([
            'reserved_at' => now()->setDate(2026, 3, 15)->setTime(10, 0),
            'reserved_until' => now()->setDate(2026, 3, 15)->setTime(12, 0),
        ]);

        $product = Finance::productFor($reservation);

        expect($product->description)->toContain('Practice Space');
        expect($product->description)->toContain('2');
        expect($product->description)->toContain('Mar 15, 2026');
    });
});

// =========================================================================
// ProcessingFeeProduct
// =========================================================================

describe('ProcessingFeeProduct', function () {
    it('computes exact pass-through fee for a given subtotal', function () {
        // Default config: 290 bps (2.9%) + 30 cents
        // charge = ceil((3000 + 30) / (1 - 0.029)) = ceil(3120.49) = 3121
        // fee = 3121 - 3000 = 121
        expect(ProcessingFeeProduct::computeFee(3000))->toBe(121);
    });

    it('computes fee correctly for zero subtotal', function () {
        // charge = ceil(30 / 0.971) = ceil(30.90) = 31
        // fee = 31 - 0 = 31
        expect(ProcessingFeeProduct::computeFee(0))->toBe(31);
    });

    it('ensures merchant receives the full subtotal after Stripe takes its cut', function () {
        // Verify the pass-through property: for a range of subtotals,
        // Stripe's cut of the total charge should never exceed the fee.
        foreach ([500, 1000, 1500, 3000, 10000, 25000] as $subtotal) {
            $fee = ProcessingFeeProduct::computeFee($subtotal);
            $totalCharge = $subtotal + $fee;

            // Stripe takes: floor(totalCharge * 0.029) + 30
            // (Stripe rounds down / truncates on their side)
            $stripeCut = (int) floor($totalCharge * 0.029) + 30;

            $merchantReceives = $totalCharge - $stripeCut;

            expect($merchantReceives)->toBeGreaterThanOrEqual(
                $subtotal,
                "Subtotal {$subtotal}: merchant receives {$merchantReceives}, expected >= {$subtotal}"
            );
        }
    });

    it('has unit "fee"', function () {
        expect(ProcessingFeeProduct::getUnit())->toBe('fee');
    });

    it('has no eligible wallets', function () {
        expect(ProcessingFeeProduct::getEligibleWallets())->toBe([]);
    });
});

// =========================================================================
// EquipmentLoanProduct pricing
// =========================================================================

describe('EquipmentLoanProduct pricing', function () {
    it('returns 1 billable unit (flat fee model)', function () {
        expect(EquipmentLoanProduct::getBillableUnits())->toBe(1.0);
    });

    it('returns equipment_credits as eligible wallet', function () {
        expect(EquipmentLoanProduct::getEligibleWallets())->toBe(['equipment_credits']);
    });

    it('has unit "loan"', function () {
        expect(EquipmentLoanProduct::getUnit())->toBe('loan');
    });
});

// =========================================================================
// TicketProduct pricing
// =========================================================================

describe('TicketProduct pricing', function () {
    it('has no eligible wallets', function () {
        expect(TicketProduct::getEligibleWallets())->toBe([]);
    });

    it('has unit "ticket"', function () {
        expect(TicketProduct::getUnit())->toBe('ticket');
    });

    it('returns zero billable units when no model is bound', function () {
        expect(TicketProduct::getBillableUnits())->toBe(0.0);
    });
});
