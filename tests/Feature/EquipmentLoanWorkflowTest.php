<?php

use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\User;
use App\States\Equipment\Available;
use App\States\Equipment\Loaned;
use App\States\Equipment\Maintenance;
use App\States\EquipmentLoan\Requested;
use App\States\EquipmentLoan\StaffPreparing;
use App\States\EquipmentLoan\ReadyForPickup;
use App\States\EquipmentLoan\CheckedOut;
use App\States\EquipmentLoan\Overdue;
use App\States\EquipmentLoan\DropoffScheduled;
use App\States\EquipmentLoan\StaffProcessingReturn;
use App\States\EquipmentLoan\Returned;
use App\States\EquipmentLoan\Cancelled;

describe('Equipment Loan Workflow', function () {
    beforeEach(function () {
        $this->equipment = Equipment::factory()->create([
            'name' => 'Test Guitar',
            'status' => 'available',
            'ownership_status' => 'cmc_owned',
            'state' => Available::class,
        ]);
        
        $this->member = User::factory()->create(['name' => 'Test Member']);
        $this->staff = User::factory()->create(['name' => 'Test Staff']);
    });

    describe('Loan Request Process', function () {
        it('creates a loan request in requested state', function () {
            $loan = EquipmentLoan::factory()->create([
                'equipment_id' => $this->equipment->id,
                'borrower_id' => $this->member->id,
                'state' => Requested::class,
            ]);

            expect($loan->state)->toBeInstanceOf(Requested::class);
            expect($loan->state->description())->toBe('Member has requested loan - awaiting staff preparation');
            expect($loan->state->requiresStaffAction())->toBeTrue();
            expect($loan->state->canBeCancelledByMember())->toBeTrue();
        });

        it('allows member to cancel request in early stages', function () {
            $loan = EquipmentLoan::factory()->create([
                'equipment_id' => $this->equipment->id,
                'borrower_id' => $this->member->id,
                'state' => Requested::class,
            ]);

            expect($loan->state->canTransitionTo(Cancelled::class))->toBeTrue();
            
            $loan->state->transitionTo(Cancelled::class);
            
            expect($loan->fresh()->state)->toBeInstanceOf(Cancelled::class);
        });
    });

    describe('Staff Preparation Process', function () {
        it('transitions from requested to staff preparing', function () {
            $loan = EquipmentLoan::factory()->create([
                'equipment_id' => $this->equipment->id,
                'borrower_id' => $this->member->id,
                'state' => Requested::class,
            ]);

            expect($loan->state->canTransitionTo(StaffPreparing::class))->toBeTrue();
            
            $loan->state->transitionTo(StaffPreparing::class);
            
            expect($loan->fresh()->state)->toBeInstanceOf(StaffPreparing::class);
            expect($loan->state->description())->toBe('Staff is preparing equipment - checking condition and taking photos');
        });

        it('transitions to ready for pickup after preparation', function () {
            $loan = EquipmentLoan::factory()->create([
                'equipment_id' => $this->equipment->id,
                'borrower_id' => $this->member->id,
                'state' => StaffPreparing::class,
            ]);

            $loan->state->transitionTo(ReadyForPickup::class);
            
            expect($loan->fresh()->state)->toBeInstanceOf(ReadyForPickup::class);
            expect($loan->state->requiresMemberAction())->toBeTrue();
        });
    });

    describe('Equipment Checkout Process', function () {
        it('transitions to checked out when member picks up', function () {
            $loan = EquipmentLoan::factory()->create([
                'equipment_id' => $this->equipment->id,
                'borrower_id' => $this->member->id,
                'state' => ReadyForPickup::class,
                'checked_out_at' => null,
            ]);

            $loan->update([
                'state' => CheckedOut::class,
                'checked_out_at' => now(),
                'due_at' => now()->addDays(7),
            ]);

            expect($loan->fresh()->state)->toBeInstanceOf(CheckedOut::class);
            expect($loan->checked_out_at)->not->toBeNull();
            expect($loan->due_at)->not->toBeNull();
        });

        it('automatically becomes overdue after due date', function () {
            $loan = EquipmentLoan::factory()->create([
                'equipment_id' => $this->equipment->id,
                'borrower_id' => $this->member->id,
                'state' => CheckedOut::class,
                'checked_out_at' => now()->subDays(10),
                'due_at' => now()->subDays(3),
            ]);

            expect($loan->state->canTransitionTo(Overdue::class))->toBeTrue();
            
            $loan->state->transitionTo(Overdue::class);
            
            expect($loan->fresh()->state)->toBeInstanceOf(Overdue::class);
            expect($loan->is_overdue)->toBeTrue();
        });
    });

    describe('Return Scheduling Process', function () {
        it('allows scheduling dropoff from checked out state', function () {
            $loan = EquipmentLoan::factory()->create([
                'equipment_id' => $this->equipment->id,
                'borrower_id' => $this->member->id,
                'state' => CheckedOut::class,
            ]);

            $loan->state->transitionTo(DropoffScheduled::class);
            
            expect($loan->fresh()->state)->toBeInstanceOf(DropoffScheduled::class);
            expect($loan->state->description())->toBe('Member has scheduled equipment dropoff');
        });

        it('allows rescheduling dropoff', function () {
            $loan = EquipmentLoan::factory()->create([
                'equipment_id' => $this->equipment->id,
                'borrower_id' => $this->member->id,
                'state' => DropoffScheduled::class,
            ]);

            // Member can reschedule by going back to checked out
            expect($loan->state->canTransitionTo(CheckedOut::class))->toBeTrue();
            
            $loan->state->transitionTo(CheckedOut::class);
            
            expect($loan->fresh()->state)->toBeInstanceOf(CheckedOut::class);
        });
    });

    describe('Return Processing', function () {
        it('processes return inspection', function () {
            $loan = EquipmentLoan::factory()->create([
                'equipment_id' => $this->equipment->id,
                'borrower_id' => $this->member->id,
                'state' => DropoffScheduled::class,
            ]);

            $loan->state->transitionTo(StaffProcessingReturn::class);
            
            expect($loan->fresh()->state)->toBeInstanceOf(StaffProcessingReturn::class);
            expect($loan->state->requiresStaffAction())->toBeTrue();
        });

        it('completes successful return', function () {
            $loan = EquipmentLoan::factory()->create([
                'equipment_id' => $this->equipment->id,
                'borrower_id' => $this->member->id,
                'state' => StaffProcessingReturn::class,
            ]);

            $loan->update([
                'state' => Returned::class,
                'returned_at' => now(),
                'condition_in' => 'good',
            ]);

            expect($loan->fresh()->state)->toBeInstanceOf(Returned::class);
            expect($loan->returned_at)->not->toBeNull();
            expect($loan->is_returned)->toBeTrue();
        });
    });

    describe('Equipment State Integration', function () {
        it('updates equipment state during loan lifecycle', function () {
            $loan = EquipmentLoan::factory()->create([
                'equipment_id' => $this->equipment->id,
                'borrower_id' => $this->member->id,
                'state' => CheckedOut::class,
            ]);

            // Equipment should be marked as loaned when checked out
            $this->equipment->update(['state' => Loaned::class]);
            
            expect($this->equipment->fresh()->state)->toBeInstanceOf(Loaned::class);

            // Equipment returns to available when loan is returned
            $loan->update(['state' => Returned::class]);
            $this->equipment->update(['state' => Available::class]);
            
            expect($this->equipment->fresh()->state)->toBeInstanceOf(Available::class);
        });

        it('handles equipment going to maintenance after damage', function () {
            $loan = EquipmentLoan::factory()->create([
                'equipment_id' => $this->equipment->id,
                'borrower_id' => $this->member->id,
                'state' => Returned::class,
                'condition_in' => 'damaged',
            ]);

            // Equipment should go to maintenance if damaged
            $this->equipment->update(['state' => Maintenance::class]);
            
            expect($this->equipment->fresh()->state)->toBeInstanceOf(Maintenance::class);
        });
    });

    describe('Loan Calculations', function () {
        it('calculates days out correctly', function () {
            $loan = EquipmentLoan::factory()->create([
                'equipment_id' => $this->equipment->id,
                'borrower_id' => $this->member->id,
                'checked_out_at' => now()->subDays(5),
                'returned_at' => null,
            ]);

            expect($loan->days_out)->toBe(5);
        });

        it('calculates days overdue correctly', function () {
            $loan = EquipmentLoan::factory()->create([
                'equipment_id' => $this->equipment->id,
                'borrower_id' => $this->member->id,
                'checked_out_at' => now()->subDays(10),
                'due_at' => now()->subDays(3),
                'returned_at' => null,
                'state' => Overdue::class,
            ]);

            expect($loan->days_overdue)->toBe(3);
        });

        it('calculates total fees correctly', function () {
            $loan = EquipmentLoan::factory()->create([
                'equipment_id' => $this->equipment->id,
                'borrower_id' => $this->member->id,
                'security_deposit' => '25.00',
                'rental_fee' => '15.00',
            ]);

            expect($loan->total_fees)->toBe('40.00');
        });
    });

    describe('Business Logic', function () {
        it('tracks loan history per equipment', function () {
            $equipment = Equipment::factory()->create();
            
            $loan1 = EquipmentLoan::factory()->returned()->create([
                'equipment_id' => $equipment->id,
            ]);
            
            $loan2 = EquipmentLoan::factory()->checkedOut()->create([
                'equipment_id' => $equipment->id,
            ]);

            expect($equipment->loans)->toHaveCount(2);
            expect($equipment->currentLoan->id)->toBe($loan2->id);
        });

        it('prevents multiple active loans for same equipment', function () {
            $activeLoan = EquipmentLoan::factory()->checkedOut()->create([
                'equipment_id' => $this->equipment->id,
            ]);

            expect($this->equipment->currentLoan->id)->toBe($activeLoan->id);
            expect($this->equipment->is_available)->toBeFalse();
        });

        it('handles loan cancellation properly', function () {
            $loan = EquipmentLoan::factory()->requested()->create([
                'equipment_id' => $this->equipment->id,
                'borrower_id' => $this->member->id,
            ]);

            $loan->update([
                'state' => Cancelled::class,
            ]);
            
            expect($loan->fresh()->state)->toBeInstanceOf(Cancelled::class);
            expect($this->equipment->fresh()->is_available)->toBeTrue();
        });
    });
});