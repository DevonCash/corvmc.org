<?php

use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\User;
use App\Services\EquipmentService;
use App\States\Equipment\Available;
use App\States\Equipment\Loaned;
use App\States\EquipmentLoan\Requested;
use App\States\EquipmentLoan\StaffPreparing;
use App\States\EquipmentLoan\ReadyForPickup;
use App\States\EquipmentLoan\CheckedOut;
use App\States\EquipmentLoan\Overdue;
use App\States\EquipmentLoan\DropoffScheduled;
use App\States\EquipmentLoan\StaffProcessingReturn;
use App\States\EquipmentLoan\Returned;
use App\States\EquipmentLoan\Cancelled;
use Carbon\Carbon;

describe('Equipment Longitudinal Workflow Tests', function () {
    beforeEach(function () {
        $this->equipmentService = app(EquipmentService::class);
        
        $this->equipment = Equipment::factory()->create([
            'name' => 'Workflow Test Guitar',
            'type' => 'guitar',
            'status' => 'available',
            'ownership_status' => 'cmc_owned',
            'condition' => 'excellent',
            'state' => Available::class,
        ]);
        
        $this->member = User::factory()->create(['name' => 'Test Member']);
        $this->staff = User::factory()->create(['name' => 'Test Staff']);
    });

    describe('Complete Reservation to Return Workflow', function () {
        it('follows the full equipment reservation lifecycle successfully', function () {
            // === PHASE 1: MEMBER CREATES FUTURE RESERVATION ===
            $reservedFrom = now()->addDays(3)->setTime(14, 0); // 2 PM in 3 days
            $dueAt = now()->addDays(8)->setTime(18, 0); // 6 PM in 8 days
            
            $loan = $this->equipmentService->createReservation(
                $this->equipment,
                $this->member,
                $reservedFrom,
                $dueAt,
                25.00, // security deposit
                15.00, // rental fee
                'Band practice session'
            );

            // Verify initial reservation state
            expect($loan->state)->toBeInstanceOf(Requested::class);
            expect($loan->reserved_from->format('Y-m-d H:i'))->toBe($reservedFrom->format('Y-m-d H:i'));
            expect($loan->due_at->format('Y-m-d H:i'))->toBe($dueAt->format('Y-m-d H:i'));
            expect($loan->checked_out_at)->toBeNull();
            expect($loan->condition_out)->toBeNull(); // Not set until staff preparation
            expect($loan->security_deposit)->toBe('25.00');
            expect($loan->rental_fee)->toBe('15.00');
            expect($loan->notes)->toBe('Band practice session');
            
            // Equipment should still be available but reserved
            expect($this->equipment->fresh()->status)->toBe('available');
            expect($loan->is_reservation_upcoming)->toBeTrue();
            expect($loan->is_reservation_active)->toBeFalse();

            // === PHASE 2: STAFF PREPARATION PROCESS ===
            // Staff begins preparation
            $loan->state->transitionTo(StaffPreparing::class);
            expect($loan->fresh()->state)->toBeInstanceOf(StaffPreparing::class);
            
            // Staff inspects equipment and sets condition
            $loan->update([
                'condition_out' => 'good',
                'notes' => $loan->notes . ' - Staff prepared, minor wear on strings'
            ]);

            // Staff marks equipment ready for pickup
            $loan->state->transitionTo(ReadyForPickup::class);
            expect($loan->fresh()->state)->toBeInstanceOf(ReadyForPickup::class);
            expect($loan->condition_out)->toBe('good');

            // === PHASE 3: MEMBER PICKUP ===
            // Time advances to pickup day
            Carbon::setTestNow($reservedFrom->copy());
            expect($loan->is_reservation_active)->toBeTrue();
            expect($loan->is_reservation_upcoming)->toBeFalse();

            // Member arrives for pickup - equipment is checked out
            $loan->update([
                'state' => CheckedOut::class,
                'checked_out_at' => now()
            ]);

            $loanAfterCheckout = $loan->fresh();
            expect($loanAfterCheckout->state)->toBeInstanceOf(CheckedOut::class);
            expect($loanAfterCheckout->checked_out_at)->not->toBeNull();
            expect($loanAfterCheckout->is_active)->toBeTrue();
            expect($loanAfterCheckout->is_overdue)->toBeFalse();

            // Equipment status should be updated
            $this->equipment->update(['status' => 'checked_out', 'state' => Loaned::class]);
            expect($this->equipment->fresh()->status)->toBe('checked_out');

            // === PHASE 4: EQUIPMENT IN USE ===
            // Time passes during the loan period
            Carbon::setTestNow($reservedFrom->copy()->addDays(3)); // Midway through loan
            expect($loanAfterCheckout->is_overdue)->toBeFalse();
            expect($loanAfterCheckout->days_out)->toBe(3);

            // === PHASE 5: MEMBER SCHEDULES RETURN ===
            // Member schedules return before due date
            $returnDate = $dueAt->copy()->subHours(2); // 2 hours before due
            Carbon::setTestNow($returnDate);
            
            $loanAfterCheckout->state->transitionTo(DropoffScheduled::class);
            expect($loanAfterCheckout->fresh()->state)->toBeInstanceOf(DropoffScheduled::class);

            // === PHASE 6: EQUIPMENT RETURN PROCESSING ===
            // Member brings equipment back
            $loanAfterCheckout->state->transitionTo(StaffProcessingReturn::class);
            expect($loanAfterCheckout->fresh()->state)->toBeInstanceOf(StaffProcessingReturn::class);

            // Staff inspects returned equipment
            $loanAfterCheckout->update([
                'condition_in' => 'good',
                'damage_notes' => null, // No damage found
                'returned_at' => now()
            ]);

            // === PHASE 7: SUCCESSFUL COMPLETION ===
            // Staff completes the return process
            $loanAfterCheckout->state->transitionTo(Returned::class);
            
            $finalLoan = $loanAfterCheckout->fresh();
            expect($finalLoan->state)->toBeInstanceOf(Returned::class);
            expect($finalLoan->returned_at)->not->toBeNull();
            expect($finalLoan->condition_in)->toBe('good');
            expect($finalLoan->is_returned)->toBeTrue();
            expect($finalLoan->is_active)->toBeFalse();

            // Equipment should be available again
            $this->equipment->update([
                'status' => 'available',
                'condition' => $finalLoan->condition_in,
                'state' => Available::class
            ]);
            
            expect($this->equipment->fresh()->status)->toBe('available');
            expect($this->equipment->fresh()->condition)->toBe('good');

            // === VERIFICATION: COMPLETE LOAN HISTORY ===
            $completedLoan = $finalLoan;
            expect($completedLoan->days_out)->toBeGreaterThan(0);
            expect($completedLoan->days_overdue)->toBe(0); // Returned on time
            expect($completedLoan->total_fees)->toBe('40.00'); // 25 + 15

            // Reset time
            Carbon::setTestNow();
        });
    });

    describe('Overdue Equipment Workflow', function () {
        it('handles overdue equipment and late return properly', function () {
            // === SETUP: CHECKED OUT EQUIPMENT ===
            $checkoutDate = now()->subDays(10);
            $originalDueDate = now()->subDays(3); // 3 days overdue
            
            $loan = EquipmentLoan::factory()->create([
                'equipment_id' => $this->equipment->id,
                'borrower_id' => $this->member->id,
                'reserved_from' => $checkoutDate,
                'checked_out_at' => $checkoutDate,
                'due_at' => $originalDueDate,
                'condition_out' => 'excellent',
                'state' => CheckedOut::class,
                'security_deposit' => '50.00',
                'rental_fee' => '20.00'
            ]);

            // === PHASE 1: EQUIPMENT BECOMES OVERDUE ===
            expect($loan->is_overdue)->toBeTrue();
            expect($loan->days_overdue)->toBe(3);

            // System or staff marks as overdue
            $loan->markOverdue();
            expect($loan->fresh()->state)->toBeInstanceOf(Overdue::class);

            // === PHASE 2: MEMBER FINALLY SCHEDULES RETURN ===
            $lateReturnDate = now(); // Return today (3 days late)
            Carbon::setTestNow($lateReturnDate);

            $loan->state->transitionTo(DropoffScheduled::class);
            expect($loan->fresh()->state)->toBeInstanceOf(DropoffScheduled::class);

            // === PHASE 3: LATE RETURN WITH DAMAGE ===
            $loan->state->transitionTo(StaffProcessingReturn::class);

            // Staff finds some damage during inspection
            $loan->update([
                'condition_in' => 'fair', // Degraded condition
                'damage_notes' => 'Scratches on body, loose tuning peg',
                'returned_at' => now()
            ]);

            // === PHASE 4: COMPLETION WITH PENALTIES ===
            $loan->state->transitionTo(Returned::class);
            
            $finalLoan = $loan->fresh();
            expect($finalLoan->state)->toBeInstanceOf(Returned::class);
            expect($finalLoan->days_overdue)->toBe(3);
            expect($finalLoan->condition_in)->toBe('fair');
            expect($finalLoan->damage_notes)->toContain('Scratches');

            // Equipment goes to maintenance due to damage
            $this->equipment->update([
                'status' => 'maintenance',
                'condition' => 'fair'
            ]);

            expect($this->equipment->fresh()->status)->toBe('maintenance');

            // Reset time
            Carbon::setTestNow();
        });
    });

    describe('Cancellation Workflow', function () {
        it('handles reservation cancellation at various stages', function () {
            // === PHASE 1: EARLY CANCELLATION (REQUESTED STATE) ===
            $futureReservation = $this->equipmentService->createReservation(
                $this->equipment,
                $this->member,
                now()->addDays(5),
                now()->addDays(10)
            );

            expect($futureReservation->state)->toBeInstanceOf(Requested::class);
            
            // Member cancels early
            $result = $this->equipmentService->cancelReservation($futureReservation);
            expect($result)->toBeTrue();
            expect($futureReservation->fresh()->state)->toBeInstanceOf(Cancelled::class);

            // === PHASE 2: CANCELLATION DURING STAFF PREPARATION ===
            $secondReservation = $this->equipmentService->createReservation(
                $this->equipment,
                $this->member,
                now()->addDays(7),
                now()->addDays(12)
            );

            // Staff begins preparation
            $secondReservation->state->transitionTo(StaffPreparing::class);
            expect($secondReservation->fresh()->state)->toBeInstanceOf(StaffPreparing::class);

            // Member changes mind and cancels
            $secondReservation->state->transitionTo(Cancelled::class);
            expect($secondReservation->fresh()->state)->toBeInstanceOf(Cancelled::class);

            // === PHASE 3: CANCELLATION AFTER CHECKOUT (SHOULD FAIL) ===
            $checkedOutLoan = EquipmentLoan::factory()->checkedOut()->create([
                'equipment_id' => $this->equipment->id,
                'borrower_id' => $this->member->id,
            ]);

            expect($checkedOutLoan->state)->toBeInstanceOf(CheckedOut::class);
            expect($checkedOutLoan->checked_out_at)->not->toBeNull();

            // Should not be able to cancel once checked out
            expect(function () use ($checkedOutLoan) {
                $this->equipmentService->cancelReservation($checkedOutLoan);
            })->toThrow(Exception::class, 'Cannot cancel reservation - equipment has already been checked out.');

            // === VERIFICATION: EQUIPMENT AVAILABILITY ===
            // After cancellations, equipment should be available
            expect($this->equipment->fresh()->status)->toBe('available');
        });
    });

    describe('Multi-User Reservation Conflicts', function () {
        it('prevents conflicts and manages sequential reservations', function () {
            $member2 = User::factory()->create(['name' => 'Second Member']);

            // === PHASE 1: FIRST MEMBER RESERVES ===
            $firstReservation = $this->equipmentService->createReservation(
                $this->equipment,
                $this->member,
                now()->addDays(5),
                now()->addDays(10),
                0, 0,
                'First member practice'
            );

            expect($firstReservation)->toBeInstanceOf(EquipmentLoan::class);

            // === PHASE 2: SECOND MEMBER TRIES CONFLICTING RESERVATION ===
            expect(function () use ($member2) {
                $this->equipmentService->createReservation(
                    $this->equipment,
                    $member2,
                    now()->addDays(7), // Overlaps with first reservation
                    now()->addDays(12)
                );
            })->toThrow(Exception::class, 'Equipment is not available for the requested period.');

            // === PHASE 3: SECOND MEMBER BOOKS AFTER FIRST ===
            $secondReservation = $this->equipmentService->createReservation(
                $this->equipment,
                $member2,
                now()->addDays(10)->addMinutes(1), // Starts 1 minute after first ends
                now()->addDays(15),
                0, 0,
                'Second member session'
            );

            expect($secondReservation)->toBeInstanceOf(EquipmentLoan::class);
            expect($secondReservation->reserved_from)
                ->toBeGreaterThan($firstReservation->due_at);

            // === PHASE 4: VERIFY SEQUENTIAL WORKFLOW ===
            // First member completes their loan
            Carbon::setTestNow($firstReservation->reserved_from);
            
            $firstReservation->update([
                'state' => CheckedOut::class,
                'checked_out_at' => now(),
                'condition_out' => 'excellent'
            ]);

            // Time advances to due date
            Carbon::setTestNow($firstReservation->due_at);
            
            $firstReservation->update([
                'state' => Returned::class,
                'returned_at' => now(),
                'condition_in' => 'excellent'
            ]);

            // Time advances to second member's reservation start
            Carbon::setTestNow($secondReservation->reserved_from);
            
            // Second member's reservation should now be ready
            expect($secondReservation->fresh()->is_reservation_active)->toBeTrue();

            // Reset time
            Carbon::setTestNow();
        });
    });

    describe('Data Integrity and Audit Trail', function () {
        it('maintains complete audit trail throughout workflow', function () {
            // Create a reservation and track all changes
            $loan = $this->equipmentService->createReservation(
                $this->equipment,
                $this->member,
                now()->addDays(2),
                now()->addDays(7),
                30.00,
                10.00,
                'Audit trail test'
            );

            $originalLoanId = $loan->id;

            // === TRACK STATE TRANSITIONS ===
            $stateHistory = [];
            
            // Initial state
            $stateHistory[] = [
                'state' => $loan->state::class,
                'timestamp' => now(),
                'checked_out_at' => $loan->checked_out_at,
                'returned_at' => $loan->returned_at
            ];

            // Staff preparation
            $loan->state->transitionTo(StaffPreparing::class);
            $loan->update(['condition_out' => 'good']);
            $stateHistory[] = [
                'state' => $loan->fresh()->state::class,
                'timestamp' => now(),
                'condition_out' => $loan->condition_out
            ];

            // Ready for pickup
            $loan->state->transitionTo(ReadyForPickup::class);
            $stateHistory[] = [
                'state' => $loan->fresh()->state::class,
                'timestamp' => now()
            ];

            // Checkout
            Carbon::setTestNow($loan->reserved_from);
            $loan->update([
                'state' => CheckedOut::class,
                'checked_out_at' => now()
            ]);
            $stateHistory[] = [
                'state' => $loan->fresh()->state::class,
                'timestamp' => now(),
                'checked_out_at' => $loan->checked_out_at
            ];

            // Return
            Carbon::setTestNow($loan->due_at->subHours(1));
            $loan->update([
                'state' => Returned::class,
                'returned_at' => now(),
                'condition_in' => 'good'
            ]);
            $stateHistory[] = [
                'state' => $loan->fresh()->state::class,
                'timestamp' => now(),
                'returned_at' => $loan->returned_at,
                'condition_in' => $loan->condition_in
            ];

            // === VERIFY AUDIT TRAIL ===
            $finalLoan = EquipmentLoan::find($originalLoanId);
            
            // Check all key timestamps are preserved
            expect($finalLoan->reserved_from)->not->toBeNull();
            expect($finalLoan->checked_out_at)->not->toBeNull();
            expect($finalLoan->returned_at)->not->toBeNull();
            expect($finalLoan->due_at)->not->toBeNull();

            // Check conditions are tracked
            expect($finalLoan->condition_out)->toBe('good');
            expect($finalLoan->condition_in)->toBe('good');

            // Check financial data preserved
            expect($finalLoan->security_deposit)->toBe('30.00');
            expect($finalLoan->rental_fee)->toBe('10.00');

            // Check relationships intact
            expect($finalLoan->equipment_id)->toBe($this->equipment->id);
            expect($finalLoan->borrower_id)->toBe($this->member->id);

            // Verify we went through all expected states
            $expectedStates = [
                Requested::class,
                StaffPreparing::class,
                ReadyForPickup::class,
                CheckedOut::class,
                Returned::class
            ];

            expect(count($stateHistory))->toBe(count($expectedStates));
            
            foreach ($expectedStates as $index => $expectedState) {
                expect($stateHistory[$index]['state'])->toBe($expectedState);
            }

            // Reset time
            Carbon::setTestNow();
        });
    });
});