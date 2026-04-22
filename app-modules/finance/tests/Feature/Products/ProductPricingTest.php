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
        expect(RehearsalProduct::pricePerUnit())->toBe(1500);
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
        expect(RehearsalProduct::eligibleWallets())->toBe(['free_hours']);
    });

    it('has unit "hour"', function () {
        expect(RehearsalProduct::unit())->toBe('hour');
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
    it('computes fee from config for a given subtotal', function () {
        // Default config: 290 bps (2.9%) + 30 cents
        // On a $30.00 subtotal (3000 cents): ceil(3000 * 290 / 10000) + 30
        //   = ceil(87) + 30 = 87 + 30 = 117 cents ($1.17)
        expect(ProcessingFeeProduct::computeFee(3000))->toBe(117);
    });

    it('computes fee correctly for zero subtotal', function () {
        // ceil(0) + 30 = 30 cents
        expect(ProcessingFeeProduct::computeFee(0))->toBe(30);
    });

    it('rounds up fractional bps portion', function () {
        // 1000 cents: ceil(1000 * 290 / 10000) + 30 = ceil(29) + 30 = 59
        expect(ProcessingFeeProduct::computeFee(1000))->toBe(59);

        // 1001 cents: ceil(1001 * 290 / 10000) + 30 = ceil(29.029) + 30 = 30 + 30 = 60
        expect(ProcessingFeeProduct::computeFee(1001))->toBe(60);
    });

    it('has unit "fee"', function () {
        expect(ProcessingFeeProduct::unit())->toBe('fee');
    });

    it('has no eligible wallets', function () {
        expect(ProcessingFeeProduct::eligibleWallets())->toBe([]);
    });
});

// =========================================================================
// EquipmentLoanProduct pricing
// =========================================================================

describe('EquipmentLoanProduct pricing', function () {
    it('returns 1 billable unit (flat fee model)', function () {
        expect(EquipmentLoanProduct::billableUnits())->toBe(1.0);
    });

    it('returns equipment_credits as eligible wallet', function () {
        expect(EquipmentLoanProduct::eligibleWallets())->toBe(['equipment_credits']);
    });

    it('has unit "loan"', function () {
        expect(EquipmentLoanProduct::unit())->toBe('loan');
    });
});

// =========================================================================
// TicketProduct pricing
// =========================================================================

describe('TicketProduct pricing', function () {
    it('has no eligible wallets', function () {
        expect(TicketProduct::eligibleWallets())->toBe([]);
    });

    it('has unit "ticket"', function () {
        expect(TicketProduct::unit())->toBe('ticket');
    });

    it('returns zero billable units when no model is bound', function () {
        expect(TicketProduct::billableUnits())->toBe(0.0);
    });
});
