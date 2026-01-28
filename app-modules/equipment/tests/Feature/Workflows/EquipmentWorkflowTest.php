<?php

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Equipment\Actions\CheckoutToMember;
use CorvMC\Equipment\Actions\GetStatistics;
use CorvMC\Equipment\Actions\MarkOverdue;
use CorvMC\Equipment\Actions\MarkReturnedToOwner;
use CorvMC\Equipment\Actions\ProcessReturn;
use CorvMC\Equipment\Models\Equipment;
use CorvMC\Equipment\Models\EquipmentLoan;
use CorvMC\Equipment\States\EquipmentLoan\CheckedOut;
use CorvMC\Equipment\States\EquipmentLoan\Overdue;
use CorvMC\Equipment\States\EquipmentLoan\Returned;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

describe('Equipment Workflow: Checkout Flow', function () {
    it('checks out equipment to a member', function () {
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->available()->create([
            'loanable' => true,
        ]);

        $dueDate = Carbon::now()->addDays(7);

        $loan = CheckoutToMember::run(
            equipment: $equipment,
            borrower: $borrower,
            dueDate: $dueDate,
            conditionOut: 'good',
            securityDeposit: 50.00,
            rentalFee: 10.00,
            notes: 'Band practice equipment'
        );

        expect($loan)->toBeInstanceOf(EquipmentLoan::class);
        expect($loan->borrower_id)->toBe($borrower->id);
        expect($loan->equipment_id)->toBe($equipment->id);
        expect($loan->condition_out)->toBe('good');
        expect((float) $loan->security_deposit)->toBe(50.00);
        expect((float) $loan->rental_fee)->toBe(10.00);
        expect($loan->checked_out_at)->not->toBeNull();
        expect($loan->state)->toBeInstanceOf(CheckedOut::class);

        // Equipment status should be updated
        expect($equipment->fresh()->status)->toBe('checked_out');
    });

    it('records checkout timestamp', function () {
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->available()->create([
            'loanable' => true,
        ]);

        $loan = CheckoutToMember::run(
            equipment: $equipment,
            borrower: $borrower,
            dueDate: Carbon::now()->addDays(7)
        );

        // Just verify the timestamp was set and is recent
        expect($loan->checked_out_at)->not->toBeNull();
        expect($loan->checked_out_at->diffInMinutes(now()))->toBeLessThan(1);
    });
});

describe('Equipment Workflow: Return Flow', function () {
    it('processes equipment return with condition recording', function () {
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->available()->create([
            'loanable' => true,
        ]);

        // First checkout
        $loan = CheckoutToMember::run(
            equipment: $equipment,
            borrower: $borrower,
            dueDate: Carbon::now()->addDays(7),
            conditionOut: 'excellent'
        );

        // Process return
        $returnedLoan = ProcessReturn::run($loan, 'good', null);

        expect($returnedLoan->returned_at)->not->toBeNull();
        expect($returnedLoan->condition_in)->toBe('good');
        expect($returnedLoan->state)->toBeInstanceOf(Returned::class);

        // Equipment should be available again with updated condition
        $equipment->refresh();
        expect($equipment->status)->toBe('available');
        expect($equipment->condition)->toBe('good');
    });

    it('records damage notes on return', function () {
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->available()->create([
            'loanable' => true,
        ]);

        $loan = CheckoutToMember::run(
            equipment: $equipment,
            borrower: $borrower,
            dueDate: Carbon::now()->addDays(7),
            conditionOut: 'good'
        );

        $returnedLoan = ProcessReturn::run($loan, 'fair', 'Minor scratch on body');

        expect($returnedLoan->condition_in)->toBe('fair');
        expect($returnedLoan->damage_notes)->toBe('Minor scratch on body');
    });
});

describe('Equipment Workflow: Availability Check', function () {
    it('prevents checkout of unavailable equipment', function () {
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->create([
            'status' => 'maintenance',
            'loanable' => true,
        ]);

        expect(fn () => CheckoutToMember::run(
            equipment: $equipment,
            borrower: $borrower,
            dueDate: Carbon::now()->addDays(7)
        ))->toThrow(\Exception::class, 'Equipment is not available for checkout.');
    });

    it('prevents checkout of non-loanable equipment', function () {
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->create([
            'status' => 'available',
            'loanable' => false,
        ]);

        expect(fn () => CheckoutToMember::run(
            equipment: $equipment,
            borrower: $borrower,
            dueDate: Carbon::now()->addDays(7)
        ))->toThrow(\Exception::class, 'Equipment is not available for checkout.');
    });

    it('prevents double booking of equipment', function () {
        $borrower1 = User::factory()->create();
        $borrower2 = User::factory()->create();
        $equipment = Equipment::factory()->available()->create([
            'loanable' => true,
        ]);

        // First checkout succeeds
        CheckoutToMember::run(
            equipment: $equipment,
            borrower: $borrower1,
            dueDate: Carbon::now()->addDays(7)
        );

        // Second checkout should fail
        expect(fn () => CheckoutToMember::run(
            equipment: $equipment,
            borrower: $borrower2,
            dueDate: Carbon::now()->addDays(7)
        ))->toThrow(\Exception::class, 'Equipment is not available for checkout.');
    });

    it('allows checkout after return', function () {
        $borrower1 = User::factory()->create();
        $borrower2 = User::factory()->create();
        $equipment = Equipment::factory()->available()->create([
            'loanable' => true,
        ]);

        // First checkout and return
        $loan1 = CheckoutToMember::run(
            equipment: $equipment,
            borrower: $borrower1,
            dueDate: Carbon::now()->addDays(7)
        );
        ProcessReturn::run($loan1, 'good');

        // Refresh equipment to get updated status
        $equipment->refresh();
        expect($equipment->status)->toBe('available');

        // Second checkout should succeed
        $loan2 = CheckoutToMember::run(
            equipment: $equipment,
            borrower: $borrower2,
            dueDate: Carbon::now()->addDays(14)
        );

        expect($loan2)->toBeInstanceOf(EquipmentLoan::class);
        expect($loan2->borrower_id)->toBe($borrower2->id);
    });
});

describe('Equipment Workflow: Overdue Loans', function () {
    it('marks loan as overdue when past due date', function () {
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->available()->create([
            'loanable' => true,
        ]);

        // Create a loan
        $loan = CheckoutToMember::run(
            equipment: $equipment,
            borrower: $borrower,
            dueDate: Carbon::now()->addDays(7)
        );

        expect($loan->state)->toBeInstanceOf(CheckedOut::class);

        // Mark as overdue
        MarkOverdue::run($loan);

        $loan->refresh();
        expect($loan->state)->toBeInstanceOf(Overdue::class);

        // Equipment should still be checked out
        expect($equipment->fresh()->status)->toBe('checked_out');
    });
});

describe('Equipment Workflow: Statistics', function () {
    it('returns correct counts for all equipment categories', function () {
        // Create equipment with explicit states to avoid random acquisition_type
        Equipment::factory()->count(2)->available()->donated()->create(['loanable' => true]);
        Equipment::factory()->count(2)->create(['status' => 'maintenance', 'loanable' => true, 'acquisition_type' => 'purchased']);
        Equipment::factory()->loanedToCmc()->create(['status' => 'available', 'loanable' => true]);

        // Create a checked out equipment
        $borrower = User::factory()->create();
        $checkedOutEquipment = Equipment::factory()->available()->create([
            'loanable' => true,
            'acquisition_type' => 'purchased',
        ]);
        CheckoutToMember::run(
            equipment: $checkedOutEquipment,
            borrower: $borrower,
            dueDate: Carbon::now()->addDays(7)
        );

        $stats = GetStatistics::run();

        expect($stats)->toBeArray();
        expect($stats)->toHaveKey('total_equipment');
        expect($stats)->toHaveKey('available_equipment');
        expect($stats)->toHaveKey('checked_out_equipment');
        expect($stats)->toHaveKey('maintenance_equipment');
        expect($stats)->toHaveKey('active_loans');
        expect($stats)->toHaveKey('overdue_loans');
        expect($stats)->toHaveKey('donated_equipment');
        expect($stats)->toHaveKey('loaned_to_cmc');

        // Verify counts
        expect($stats['total_equipment'])->toBe(6);
        expect($stats['maintenance_equipment'])->toBe(2);
        expect($stats['checked_out_equipment'])->toBe(1);
        expect($stats['active_loans'])->toBe(1);
        expect($stats['overdue_loans'])->toBe(0);
        expect($stats['donated_equipment'])->toBe(2);
        expect($stats['loaned_to_cmc'])->toBe(1);
    });
});

describe('Equipment Workflow: Return to Owner', function () {
    it('marks equipment as returned to owner', function () {
        $equipment = Equipment::factory()->create([
            'status' => 'available',
            'ownership_status' => 'on_loan',
            'acquisition_type' => 'loaned_to_cmc',
            'loanable' => true,
        ]);

        MarkReturnedToOwner::run($equipment);

        $equipment->refresh();
        expect($equipment->ownership_status)->toBe('returned_to_owner');
        expect($equipment->status)->toBe('retired');
    });
});
