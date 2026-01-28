<?php

use CorvMC\Finance\Enums\CreditType;
use App\Models\User;
use Carbon\Carbon;
use CorvMC\Finance\Enums\ChargeStatus;
use CorvMC\SpaceManagement\Actions\Reservations\CancelReservation;
use CorvMC\SpaceManagement\Actions\Reservations\CheckTimeSlotAvailability;
use CorvMC\SpaceManagement\Actions\Reservations\ConfirmReservation;
use CorvMC\SpaceManagement\Actions\Reservations\CreateReservation;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    // Clear reservation cache to prevent test isolation issues
    \Illuminate\Support\Facades\Cache::flush();
});

describe('Reservation Workflow: Create Single Reservation', function () {
    it('creates a reservation for a regular user with correct cost calculation', function () {
        $user = User::factory()->create();
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = CreateReservation::run($user, $startTime, $endTime);

        expect($reservation)->toBeInstanceOf(RehearsalReservation::class);
        expect($reservation->reservable_id)->toBe($user->id);
        expect((float) $reservation->hours_used)->toBe(2.0);
        expect($reservation->status)->toBe(ReservationStatus::Scheduled);
        expect($reservation->charge->net_amount->getMinorAmount()->toInt())->toBe(3000); // $15/hour * 2 hours = $30
    });

    it('creates a free reservation for sustaining member using credits', function () {
        $user = User::factory()->sustainingMember()->create();
        // Give the user some free hour credits (8 blocks = 4 hours)
        $user->addCredit(8, CreditType::FreeHours, 'test_allocation', null, 'Test allocation');

        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $initialBalance = $user->getCreditBalance(CreditType::FreeHours);

        $reservation = CreateReservation::run($user, $startTime, $endTime);

        expect($reservation->charge->net_amount->getMinorAmount()->toInt())->toBe(0);
        expect((float) $reservation->free_hours_used)->toBe(2.0);
        expect($reservation->getChargeStatus())->toBe(ChargeStatus::Paid); // Free hours = auto-paid

        // Credits should be deducted (4 blocks for 2 hours)
        $newBalance = $user->fresh()->getCreditBalance(CreditType::FreeHours);
        expect($newBalance)->toBe($initialBalance - 4);
    });

    it('auto-confirms reservations less than 3 days away', function () {
        $user = User::factory()->create();
        $startTime = Carbon::now()->addDays(1)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = CreateReservation::run($user, $startTime, $endTime);

        expect($reservation->status)->toBe(ReservationStatus::Confirmed);
    });
});

describe('Reservation Workflow: Confirm Reservation', function () {
    it('confirms a scheduled reservation', function () {
        $user = User::factory()->admin()->create();
        $startTime = Carbon::now()->addDays(4)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = CreateReservation::run($user, $startTime, $endTime);
        expect($reservation->status)->toBe(ReservationStatus::Scheduled);

        // Move time forward so confirmation is allowed (within 5 days of reservation)
        $this->travel(2)->days();

        $confirmedReservation = ConfirmReservation::run($reservation);

        expect($confirmedReservation->status)->toBe(ReservationStatus::Confirmed);
    });

    it('deducts credits when confirming a reserved status reservation', function () {
        $user = User::factory()->sustainingMember()->create();
        $user->addCredit(8, CreditType::FreeHours, 'test_allocation', null, 'Test allocation');

        $startTime = Carbon::now()->addDays(4)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        // Create with Reserved status using CreateReservation to ensure Charge is created
        // Reserved status defers credit deduction until confirmation
        $reservation = CreateReservation::run($user, $startTime, $endTime, [
            'status' => ReservationStatus::Reserved,
        ]);

        // Credits should NOT be deducted yet (deferred for Reserved status)
        $initialBalance = $user->fresh()->getCreditBalance(CreditType::FreeHours);
        expect($initialBalance)->toBe(8);

        // Move time forward so confirmation is allowed
        $this->travel(2)->days();

        $confirmedReservation = ConfirmReservation::run($reservation);

        expect($confirmedReservation->status)->toBe(ReservationStatus::Confirmed);

        // Credits should now be deducted
        $newBalance = $user->fresh()->getCreditBalance(CreditType::FreeHours);
        expect($newBalance)->toBe($initialBalance - 4);
    });
});

describe('Reservation Workflow: Availability Check', function () {
    it('returns true when time slot is available', function () {
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $isAvailable = CheckTimeSlotAvailability::run($startTime, $endTime);

        expect($isAvailable)->toBeTrue();
    });

    it('returns false when time slot conflicts with existing reservation', function () {
        $user = User::factory()->create();
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        CreateReservation::run($user, $startTime, $endTime);

        // Check overlapping time slot
        $overlapStart = $startTime->copy()->addMinutes(30);
        $overlapEnd = $overlapStart->copy()->addHours(2);

        $isAvailable = CheckTimeSlotAvailability::run($overlapStart, $overlapEnd);

        expect($isAvailable)->toBeFalse();
    });

    it('excludes a specific reservation when checking availability', function () {
        $user = User::factory()->create();
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = CreateReservation::run($user, $startTime, $endTime);

        // Check the same time slot, excluding the reservation
        $isAvailable = CheckTimeSlotAvailability::run($startTime, $endTime, $reservation->id);

        expect($isAvailable)->toBeTrue();
    });
});

describe('Reservation Workflow: Cancel Reservation', function () {
    it('cancels a reservation and updates status', function () {
        $user = User::factory()->create();
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = CreateReservation::run($user, $startTime, $endTime);

        $cancelledReservation = CancelReservation::run($reservation, 'Test cancellation');

        expect($cancelledReservation->status)->toBe(ReservationStatus::Cancelled);
        expect($cancelledReservation->cancellation_reason)->toBe('Test cancellation');
    });

    it('refunds credits when cancelling a reservation with free hours', function () {
        $user = User::factory()->sustainingMember()->create();
        $user->addCredit(8, CreditType::FreeHours, 'test_allocation', null, 'Test allocation');

        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = CreateReservation::run($user, $startTime, $endTime);

        // Verify credits were deducted
        $balanceAfterCreate = $user->fresh()->getCreditBalance(CreditType::FreeHours);
        expect($balanceAfterCreate)->toBe(4); // 8 - 4 = 4

        CancelReservation::run($reservation);

        // Credits should be refunded
        $balanceAfterCancel = $user->fresh()->getCreditBalance(CreditType::FreeHours);
        expect($balanceAfterCancel)->toBe(8);
    });

    it('frees up the time slot after cancellation', function () {
        $user = User::factory()->create();
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = CreateReservation::run($user, $startTime, $endTime);

        // Verify slot is not available
        expect(CheckTimeSlotAvailability::run($startTime, $endTime))->toBeFalse();

        CancelReservation::run($reservation);

        // Slot should now be available
        expect(CheckTimeSlotAvailability::run($startTime, $endTime))->toBeTrue();
    });

    it('marks charge as cancelled when cancelling an unpaid reservation', function () {
        $user = User::factory()->create();
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = CreateReservation::run($user, $startTime, $endTime);

        // Verify charge is pending (unpaid)
        expect($reservation->charge->status)->toBe(ChargeStatus::Pending);

        CancelReservation::run($reservation);

        // Charge should be marked as Cancelled, not Refunded
        $reservation->refresh();
        expect($reservation->charge->status)->toBe(ChargeStatus::Cancelled);
    });

    it('marks charge as refunded when cancelling a paid reservation', function () {
        $user = User::factory()->create();
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = CreateReservation::run($user, $startTime, $endTime);

        // Mark the charge as paid
        $reservation->charge->markAsPaid('cash');
        expect($reservation->charge->status)->toBe(ChargeStatus::Paid);

        CancelReservation::run($reservation);

        // Charge should be marked as Refunded since payment was made
        $reservation->refresh();
        expect($reservation->charge->status)->toBe(ChargeStatus::Refunded);
    });
});
