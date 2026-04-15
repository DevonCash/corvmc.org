<?php

use CorvMC\Finance\Enums\CreditType;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use App\Models\User;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Carbon\Carbon;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\Finance\Actions\Credits\AdjustCredits;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\Finance\Actions\Payments\CalculateFeeCoverage;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\Finance\Enums\ChargeStatus;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\Finance\Actions\Payments\MarkReservationAsPaid;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Actions\Reservations\CreateReservation;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Illuminate\Support\Facades\Notification;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Brick\Money\Money;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\Finance\Facades\CreditService;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\Finance\Facades\MemberBenefitService;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\Finance\Facades\PaymentService;
use CorvMC\SpaceManagement\Models\RehearsalReservation;

beforeEach(function () {
    Notification::fake();
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

describe('Finance Workflow: Monthly Credit Allocation', function () {
    it('allocates free hour credits to sustaining member', function () {
        $user = User::factory()->sustainingMember()->create();

        // Initial balance should be 0
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(0);

        // Allocate 8 blocks (4 hours) of free credits
        MemberBenefitService::allocateMonthlyCredits($user, 8, CreditType::FreeHours);

        expect($user->fresh()->getCreditBalance(CreditType::FreeHours))->toBe(8);
    });

    it('resets free hours to new amount on monthly allocation (no rollover)', function () {
        $user = User::factory()->sustainingMember()->create();

        // First allocation
        MemberBenefitService::allocateMonthlyCredits($user, 8, CreditType::FreeHours);
        expect($user->fresh()->getCreditBalance(CreditType::FreeHours))->toBe(8);

        // Simulate using some credits
        $user->deductCredit(4, CreditType::FreeHours, 'test_usage', null);
        expect($user->fresh()->getCreditBalance(CreditType::FreeHours))->toBe(4);

        // Move forward one month
        $this->travel(1)->month();

        // Next monthly allocation should reset to the full amount
        MemberBenefitService::allocateMonthlyCredits($user, 8, CreditType::FreeHours);
        expect($user->fresh()->getCreditBalance(CreditType::FreeHours))->toBe(8);
    });

    it('handles mid-month tier upgrade by adding delta', function () {
        $user = User::factory()->sustainingMember()->create();

        // Initial allocation at tier 1 (8 blocks)
        MemberBenefitService::allocateMonthlyCredits($user, 8, CreditType::FreeHours);
        expect($user->fresh()->getCreditBalance(CreditType::FreeHours))->toBe(8);

        // Upgrade to tier 2 (16 blocks) mid-month - should add 8 more
        MemberBenefitService::allocateMonthlyCredits($user, 16, CreditType::FreeHours);
        expect($user->fresh()->getCreditBalance(CreditType::FreeHours))->toBe(16);
    });

    it('equipment credits roll over with cap', function () {
        $user = User::factory()->sustainingMember()->create();

        // First allocation
        MemberBenefitService::allocateMonthlyCredits($user, 50, CreditType::EquipmentCredits);
        expect($user->fresh()->getCreditBalance(CreditType::EquipmentCredits))->toBe(50);

        // Move forward one month
        $this->travel(1)->month();

        // Second allocation should add (rollover)
        MemberBenefitService::allocateMonthlyCredits($user, 50, CreditType::EquipmentCredits);
        expect($user->fresh()->getCreditBalance(CreditType::EquipmentCredits))->toBe(100);
    });
});

describe('Finance Workflow: Credit Adjustments', function () {
    it('adjusts credits via admin action', function () {
        $user = User::factory()->create();

        // Initial state - no credits
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(0);

        // Admin adds credits
        CreditService::adjust($user, 4, CreditType::FreeHours);

        expect($user->fresh()->getCreditBalance(CreditType::FreeHours))->toBe(4);
    });

    it('can deduct credits via negative adjustment', function () {
        $user = User::factory()->create();

        // Add some credits first
        $user->addCredit(10, CreditType::FreeHours, 'test_setup', null, 'Setup');

        // Deduct via adjustment
        CreditService::adjust($user, -3, CreditType::FreeHours);

        expect($user->fresh()->getCreditBalance(CreditType::FreeHours))->toBe(7);
    });
});

describe('Finance Workflow: Payment Processing', function () {
    it('marks a reservation as paid', function () {
        $user = User::factory()->create();

        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = RehearsalReservation::create([
            'reservable_type' => User::class,
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => RehearsalReservation::determineInitialStatus($user),
            'hours_used' => $startTime->diffInMinutes($endTime) / 60,
        ]);
        expect($reservation->charge->status)->toBe(ChargeStatus::Pending);

        PaymentService::markAsPaid($reservation->charge, 'cash', 'Paid in person');

        $reservation->refresh();
        expect($reservation->charge->status)->toBe(ChargeStatus::Paid);
        expect($reservation->charge->payment_method)->toBe('cash');
        expect($reservation->charge->paid_at)->not->toBeNull();
    });
});

describe('Finance Workflow: CoveredByCredits Status', function () {
    it('assigns CoveredByCredits status to credit-covered reservations', function () {
        $user = User::factory()->sustainingMember()->create();
        $user->addCredit(8, CreditType::FreeHours, 'test_allocation', null, 'Test allocation');

        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = RehearsalReservation::create([
            'reservable_type' => User::class,
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => RehearsalReservation::determineInitialStatus($user),
            'hours_used' => $startTime->diffInMinutes($endTime) / 60,
        ]);

        expect($reservation->charge->status)->toBe(ChargeStatus::CoveredByCredits);
        expect($reservation->charge->payment_method)->toBe('credits');
        expect($reservation->charge->net_amount->getMinorAmount())->toBe(0);
    });
});

describe('Finance Workflow: Fee Calculations', function () {
    it('calculates fee coverage amount for base price', function () {
        // Test with $30 base (3000 cents)
        $feeCoverage = PaymentService::calculateFeeCoverage(3000);

        expect($feeCoverage)->toBeInstanceOf(Money::class);
        // Fee coverage should be positive
        expect($feeCoverage->getMinorAmount()->toInt())->toBeGreaterThan(0);
    });

    it('calculates total with fee coverage', function () {
        $baseAmount = Money::ofMinor(3000, 'USD'); // $30.00

        $totalWithCoverage = PaymentService::calculateTotalWithFeeCoverage($baseAmount);

        expect($totalWithCoverage)->toBeInstanceOf(Money::class);
        // Total should be higher than base amount
        expect($totalWithCoverage->getMinorAmount()->toInt())->toBeGreaterThan(3000);
    });
});
