<?php

use App\Finance\Products\CompDiscountProduct;
use App\Finance\Products\EquipmentLoanProduct;
use App\Finance\Products\ManualAdjustmentProduct;
use App\Finance\Products\ProcessingFeeProduct;
use App\Finance\Products\RehearsalProduct;
use App\Finance\Products\FreeHoursDiscountProduct;
use App\Finance\Products\EquipmentCreditsDiscountProduct;
use App\Finance\Products\TicketProduct;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\FinanceManager;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\Events\Models\TicketOrder;
use CorvMC\Equipment\Models\EquipmentLoan;

// =========================================================================
// Registry — registeredTypes, isRegisteredType
// =========================================================================

describe('Product registry', function () {
    it('has all expected product types registered via AppServiceProvider', function () {
        $types = Finance::registeredTypes();

        expect($types)->toContain('rehearsal_time');
        expect($types)->toContain('event_ticket');
        expect($types)->toContain('equipment_loan');
        expect($types)->toContain('processing_fee');
        expect($types)->toContain('free_hours_discount');
        expect($types)->toContain('equipment_credits_discount');
        expect($types)->toContain('comp_discount');
        expect($types)->toContain('manual_adjustment');
    });

    it('recognises registered types', function () {
        expect(Finance::isRegisteredType('rehearsal_time'))->toBeTrue();
        expect(Finance::isRegisteredType('event_ticket'))->toBeTrue();
        expect(Finance::isRegisteredType('equipment_loan'))->toBeTrue();
        expect(Finance::isRegisteredType('processing_fee'))->toBeTrue();
    });

    it('rejects unregistered types', function () {
        expect(Finance::isRegisteredType('nonexistent_widget'))->toBeFalse();
    });
});

// =========================================================================
// productByType — category Products
// =========================================================================

describe('productByType', function () {
    it('resolves a category product by type string', function () {
        $product = Finance::productByType('processing_fee');

        expect($product)->toBeInstanceOf(ProcessingFeeProduct::class);
        expect($product->getType())->toBe('processing_fee');
        expect($product->getModel())->toBeNull();
    });

    it('resolves comp discount by type string', function () {
        $product = Finance::productByType('comp_discount');

        expect($product)->toBeInstanceOf(CompDiscountProduct::class);
    });

    it('resolves manual adjustment by type string', function () {
        $product = Finance::productByType('manual_adjustment');

        expect($product)->toBeInstanceOf(ManualAdjustmentProduct::class);
    });

    it('throws for unknown type', function () {
        Finance::productByType('nonexistent_widget');
    })->throws(\RuntimeException::class, 'No Product registered for type [nonexistent_widget]');
});

// =========================================================================
// productFor — model-backed Products
// =========================================================================

describe('productFor', function () {
    it('resolves RehearsalReservation to RehearsalProduct', function () {
        $reservation = RehearsalReservation::factory()->make();
        $product = Finance::productFor($reservation);

        expect($product)->toBeInstanceOf(RehearsalProduct::class);
        expect($product->getType())->toBe('rehearsal_time');
        expect($product->getModel())->toBe($reservation);
    });

    it('resolves TicketOrder to TicketProduct', function () {
        $ticketOrder = TicketOrder::factory()->make();
        $product = Finance::productFor($ticketOrder);

        expect($product)->toBeInstanceOf(TicketProduct::class);
        expect($product->getType())->toBe('event_ticket');
    });

    it('resolves EquipmentLoan to EquipmentLoanProduct', function () {
        $loan = EquipmentLoan::factory()->make();
        $product = Finance::productFor($loan);

        expect($product)->toBeInstanceOf(EquipmentLoanProduct::class);
        expect($product->getType())->toBe('equipment_loan');
    });

    it('throws for unregistered model', function () {
        $user = \App\Models\User::factory()->make();
        Finance::productFor($user);
    })->throws(\RuntimeException::class, 'No Product registered for model class');
});
