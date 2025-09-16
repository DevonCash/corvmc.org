<?php

use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\User;
use App\Services\EquipmentService;
use Carbon\Carbon;

beforeEach(function () {
    $this->equipmentService = app(EquipmentService::class);
    
    // Create test users
    $this->donor = User::factory()->create(['name' => 'John Donor']);
    $this->lender = User::factory()->create(['name' => 'Jane Lender']);
    $this->borrower = User::factory()->create(['name' => 'Bob Borrower']);
    $this->staff = User::factory()->create(['name' => 'Staff Member']);
});

describe('Equipment Donation & Acquisition', function () {
    test('Story 1: Member can donate equipment to CMC', function () {
        // Create donated equipment
        $equipment = Equipment::create([
            'name' => 'Fender Stratocaster',
            'type' => 'guitar',
            'brand' => 'Fender',
            'model' => 'American Standard Stratocaster',
            'serial_number' => 'US12345678',
            'description' => 'Excellent condition electric guitar',
            'condition' => 'excellent',
            'acquisition_type' => 'donated',
            'provider_id' => $this->donor->id,
            'acquisition_date' => now(),
            'ownership_status' => 'cmc_owned',
            'status' => 'available',
            'loanable' => true,
            'estimated_value' => 1200.00,
            'location' => 'Main storage',
            'acquisition_notes' => 'Donated by longtime member John',
        ]);

        expect($equipment->isDonated())->toBeTrue()
            ->and($equipment->provider->name)->toBe('John Donor')
            ->and($equipment->ownership_status)->toBe('cmc_owned')
            ->and($equipment->is_available)->toBeTrue();
    });

    test('Story 2: Member can loan equipment to CMC temporarily', function () {
        $returnDate = now()->addMonths(6);
        
        $equipment = Equipment::create([
            'name' => 'Roland TD-17KVX Drum Kit',
            'type' => 'percussion',
            'brand' => 'Roland',
            'model' => 'TD-17KVX',
            'condition' => 'good',
            'acquisition_type' => 'loaned_to_us',
            'provider_id' => $this->lender->id,
            'acquisition_date' => now(),
            'return_due_date' => $returnDate,
            'ownership_status' => 'on_loan_to_cmc',
            'status' => 'available',
            'loanable' => true,
            'estimated_value' => 1800.00,
            'acquisition_notes' => 'Loaned for 6 months while owner travels',
        ]);

        expect($equipment->isOnLoanToCmc())->toBeTrue()
            ->and($equipment->provider->name)->toBe('Jane Lender')
            ->and($equipment->return_due_date->isSameDay($returnDate))->toBeTrue()
            ->and($equipment->ownership_status)->toBe('on_loan_to_cmc');
    });

    test('Story 3: Staff can process equipment intake with external donor', function () {
        $contactData = new \App\Data\ContactData(
            visibility: 'private',
            email: 'external@donor.com',
            phone: '555-0123',
            address: '123 Music St, Portland OR'
        );

        $equipment = Equipment::create([
            'name' => 'Shure SM57 Microphone',
            'type' => 'microphone',
            'brand' => 'Shure',
            'model' => 'SM57',
            'condition' => 'good',
            'acquisition_type' => 'donated',
            'provider_contact' => $contactData,
            'acquisition_date' => now(),
            'ownership_status' => 'cmc_owned',
            'status' => 'available',
            'loanable' => true,
            'estimated_value' => 99.00,
            'acquisition_notes' => 'Donated by community member',
        ]);

        expect($equipment->provider_contact->email)->toBe('external@donor.com')
            ->and($equipment->provider_display)->toBe('external@donor.com')
            ->and($equipment->isDonated())->toBeTrue();
    });
});

describe('Equipment Lending to Members', function () {
    beforeEach(function () {
        $this->equipment = Equipment::factory()->create([
            'name' => 'Fender Bass VI',
            'type' => 'bass',
            'status' => 'available',
            'condition' => 'good',
            'acquisition_type' => 'donated',
            'provider_id' => $this->donor->id,
            'ownership_status' => 'cmc_owned',
        ]);
    });

    test('Story 4: Member can browse available equipment', function () {
        $availableEquipment = $this->equipmentService->getAllAvailable();
        
        expect($availableEquipment)->toHaveCount(1)
            ->and($availableEquipment->first()->name)->toBe('Fender Bass VI')
            ->and($availableEquipment->first()->is_available)->toBeTrue();
    });

    test('Story 5 & 6: Member can request and staff can process equipment checkout', function () {
        $dueDate = now()->addWeeks(2);
        
        // Process checkout
        $loan = $this->equipmentService->checkoutToMember(
            equipment: $this->equipment,
            borrower: $this->borrower,
            dueDate: $dueDate,
            conditionOut: 'good',
            securityDeposit: 50.00,
            rentalFee: 25.00,
            notes: 'For recording session'
        );

        expect($loan)->toBeInstanceOf(EquipmentLoan::class)
            ->and($loan->borrower->name)->toBe('Bob Borrower')
            ->and($loan->equipment->name)->toBe('Fender Bass VI')
            ->and($loan->is_active)->toBeTrue()
            ->and($loan->security_deposit)->toBe('50.00')
            ->and($loan->rental_fee)->toBe('25.00')
            ->and($this->equipment->fresh()->status)->toBe('checked_out')
            ->and($this->equipment->fresh()->is_available)->toBeFalse();
    });

    test('Story 7: Staff can process equipment return', function () {
        // First checkout the equipment
        $loan = $this->equipmentService->checkoutToMember(
            $this->equipment,
            $this->borrower,
            now()->addWeeks(2)
        );

        // Process return
        $returnedLoan = $this->equipmentService->processReturn(
            $loan,
            'good',
            'No damage observed'
        );

        expect($returnedLoan->is_returned)->toBeTrue()
            ->and($returnedLoan->condition_in)->toBe('good')
            ->and($returnedLoan->returned_at)->not()->toBeNull()
            ->and($this->equipment->fresh()->status)->toBe('available')
            ->and($this->equipment->fresh()->is_available)->toBeTrue();
    });
});

describe('Equipment Management & Maintenance', function () {
    beforeEach(function () {
        $this->equipment = Equipment::factory()->create([
            'status' => 'available',
            'condition' => 'good',
            'ownership_status' => 'cmc_owned',
        ]);
    });

    test('Story 8: Staff can track equipment maintenance', function () {
        // Mark equipment for maintenance
        $this->equipment->update([
            'status' => 'maintenance',
            'condition' => 'needs_repair',
            'notes' => 'Amp crackling, needs tube replacement'
        ]);

        expect($this->equipment->status)->toBe('maintenance')
            ->and($this->equipment->condition)->toBe('needs_repair')
            ->and($this->equipment->is_available)->toBeFalse();

        // After maintenance
        $this->equipment->update([
            'status' => 'available',
            'condition' => 'excellent',
            'notes' => 'Tubes replaced, sounds great'
        ]);

        expect($this->equipment->fresh()->is_available)->toBeTrue();
    });

    test('Story 9: Staff can manage overdue equipment', function () {
        // Create overdue loan using state machine
        $loan = EquipmentLoan::factory()->create([
            'equipment_id' => $this->equipment->id,
            'borrower_id' => $this->borrower->id,
            'due_at' => now()->subDays(5),
            'checked_out_at' => now()->subDays(7),
            'state' => \App\States\EquipmentLoan\CheckedOut::class,
        ]);

        $overdueLoans = $this->equipmentService->getOverdueLoans();

        expect($overdueLoans)->toHaveCount(1)
            ->and($overdueLoans->first()->is_overdue)->toBeTrue()
            ->and($overdueLoans->first()->days_overdue)->toBe(5);

        // Mark as overdue
        $this->equipmentService->markOverdue($loan);
        
        expect($loan->fresh()->state)->toBeInstanceOf(\App\States\EquipmentLoan\Overdue::class);
    });

    test('Story 10: Administrator can view equipment statistics', function () {
        // Create variety of equipment
        Equipment::factory()->create(['acquisition_type' => 'donated', 'estimated_value' => 500]);
        Equipment::factory()->create(['acquisition_type' => 'purchased', 'estimated_value' => 800]);
        Equipment::factory()->create(['acquisition_type' => 'loaned_to_us', 'estimated_value' => 1200]);

        $stats = $this->equipmentService->getStatistics();

        expect($stats['total_equipment'])->toBeGreaterThanOrEqual(4)
            ->and($stats['donated_equipment'])->toBeGreaterThanOrEqual(1)
            ->and($stats['loaned_to_cmc'])->toBeGreaterThanOrEqual(1);

        $valueByType = $this->equipmentService->getValueByAcquisitionType();
        
        expect($valueByType)->toHaveKey('donated')
            ->and($valueByType)->toHaveKey('purchased')
            ->and($valueByType)->toHaveKey('loaned_to_us');
    });
});

describe('Member Experience', function () {
    test('Story 11: Member can view their equipment activity', function () {
        $equipment = Equipment::factory()->create();
        
        // Create loan history
        $activeLoan = EquipmentLoan::factory()->create([
            'borrower_id' => $this->borrower->id,
            'equipment_id' => $equipment->id,
            'state' => \App\States\EquipmentLoan\CheckedOut::class,
            'checked_out_at' => now()->subDays(2),
            'due_at' => now()->addDays(5), // Future due date to ensure not overdue
        ]);

        $pastLoan = EquipmentLoan::factory()->create([
            'borrower_id' => $this->borrower->id,
            'equipment_id' => $equipment->id,
            'state' => \App\States\EquipmentLoan\Returned::class,
            'returned_at' => now()->subWeeks(1),
        ]);

        $activeLoans = $this->equipmentService->getActiveLoansForUser($this->borrower);
        
        expect($activeLoans)->toHaveCount(1)
            ->and($activeLoans->first()->id)->toBe($activeLoan->id);

        $userHasOverdue = $this->equipmentService->userHasOverdueLoans($this->borrower);
        expect($userHasOverdue)->toBeFalse();
    });

    test('Story 12: Member can report equipment damage', function () {
        $equipment = Equipment::factory()->create();
        $loan = EquipmentLoan::factory()->create([
            'borrower_id' => $this->borrower->id,
            'equipment_id' => $equipment->id,
            'condition_out' => 'good',
        ]);

        // Process return with damage
        $this->equipmentService->processReturn(
            $loan,
            'fair',
            'Small dent on back, still functional'
        );

        expect($loan->fresh()->condition_in)->toBe('fair')
            ->and($loan->fresh()->damage_notes)->toContain('Small dent')
            ->and($equipment->fresh()->condition)->toBe('fair');
    });
});

describe('Equipment Return to Owners', function () {
    test('Story 13: Staff can track equipment needing return to owners', function () {
        // Create equipment on loan to CMC that's past due
        $pastDueEquipment = Equipment::factory()->create([
            'acquisition_type' => 'loaned_to_us',
            'ownership_status' => 'on_loan_to_cmc',
            'return_due_date' => now()->subDays(10),
            'provider_id' => $this->lender->id,
        ]);

        $futureEquipment = Equipment::factory()->create([
            'acquisition_type' => 'loaned_to_us',
            'ownership_status' => 'on_loan_to_cmc',
            'return_due_date' => now()->addDays(30),
            'provider_id' => $this->lender->id,
        ]);

        $needingReturn = $this->equipmentService->getEquipmentNeedingReturn();

        expect($needingReturn)->toHaveCount(1)
            ->and($needingReturn->first()->id)->toBe($pastDueEquipment->id)
            ->and($pastDueEquipment->needsReturn())->toBeTrue()
            ->and($futureEquipment->needsReturn())->toBeFalse();

        // Mark as returned
        $this->equipmentService->markReturnedToOwner($pastDueEquipment);

        expect($pastDueEquipment->fresh()->ownership_status)->toBe('returned_to_owner')
            ->and($pastDueEquipment->fresh()->status)->toBe('retired');
    });

    test('Story 14: Administrator can track donor contributions', function () {
        // Create multiple donations from same donor
        Equipment::factory()->count(3)->create([
            'acquisition_type' => 'donated',
            'provider_id' => $this->donor->id,
            'estimated_value' => 100,
        ]);

        $donatedEquipment = $this->equipmentService->getDonatedByUser($this->donor);

        expect($donatedEquipment)->toHaveCount(3);

        $valueByType = $this->equipmentService->getValueByAcquisitionType();
        expect($valueByType['donated'])->toBeGreaterThanOrEqual(300);
    });
});

describe('Daily Automation Tasks', function () {
    test('Equipment service can process daily automation', function () {
        // Create overdue loan
        $equipment = Equipment::factory()->create();
        EquipmentLoan::factory()->create([
            'equipment_id' => $equipment->id,
            'due_at' => now()->subDays(2),
            'checked_out_at' => now()->subDays(4),
            'state' => \App\States\EquipmentLoan\CheckedOut::class,
        ]);

        // Create equipment needing return
        Equipment::factory()->create([
            'acquisition_type' => 'loaned_to_us',
            'ownership_status' => 'on_loan_to_cmc',
            'return_due_date' => now()->subDays(1),
        ]);

        $results = $this->equipmentService->processDailyTasks();

        expect($results['marked_overdue'])->toBe(1)
            ->and($results['equipment_needing_return'])->toBe(1);
    });
});

describe('Equipment Availability Logic', function () {
    test('Equipment availability is calculated correctly', function () {
        $availableEquipment = Equipment::factory()->create([
            'status' => 'available',
            'ownership_status' => 'cmc_owned',
        ]);

        $checkedOutEquipment = Equipment::factory()->create([
            'status' => 'checked_out',
            'ownership_status' => 'cmc_owned',
        ]);

        $loanedToCmcEquipment = Equipment::factory()->create([
            'status' => 'available',
            'ownership_status' => 'on_loan_to_cmc',
            'loanable' => true, // Equipment loaned to CMC can still be lent by CMC
        ]);

        expect($availableEquipment->is_available)->toBeTrue()
            ->and($checkedOutEquipment->is_available)->toBeFalse()
            ->and($loanedToCmcEquipment->is_available)->toBeTrue(); // Should be available for lending
    });

    test('Equipment with active loans is not available', function () {
        $equipment = Equipment::factory()->create([
            'status' => 'available',
            'ownership_status' => 'cmc_owned',
        ]);

        expect($equipment->is_available)->toBeTrue();

        // Create active loan
        EquipmentLoan::factory()->create([
            'equipment_id' => $equipment->id,
            'state' => \App\States\EquipmentLoan\CheckedOut::class,
            'checked_out_at' => now(),
        ]);

        expect($equipment->fresh()->is_available)->toBeFalse();
    });
});