<?php

use App\Models\Equipment;
use App\Models\EquipmentDamageReport;
use App\Models\User;
use App\States\Equipment\Available;
use App\States\Equipment\Maintenance;

describe('Equipment Maintenance Integration Tests', function () {
    beforeEach(function () {
        $this->equipment = Equipment::factory()->create([
            'name' => 'Test Guitar for Maintenance',
            'condition' => 'excellent',
            'status' => 'available',
            'loanable' => true,
            'estimated_value' => 500.00,
            'state' => Available::class,
        ]);
        
        $this->staff = User::factory()->create(['name' => 'Staff Member']);
        $this->technician = User::factory()->create(['name' => 'Technician']);
    });

    describe('Damage Report to Maintenance Workflow', function () {
        it('processes equipment through damage discovery to maintenance mode', function () {
            // === PHASE 1: CREATE DAMAGE REPORT ===
            $damageReport = EquipmentDamageReport::create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->staff->id,
                'title' => 'Tuning peg issues',
                'description' => 'Several tuning pegs are loose and need adjustment',
                'severity' => 'medium',
                'priority' => 'high',
                'status' => 'reported',
                'discovered_at' => now(),
                'estimated_cost' => 50,
            ]);

            expect($damageReport->equipment->id)->toBe($this->equipment->id)
                ->and($damageReport->is_open)->toBeTrue()
                ->and($damageReport->is_high_priority)->toBeTrue();

            // === PHASE 2: MOVE EQUIPMENT TO MAINTENANCE ===
            $this->equipment->update([
                'status' => 'maintenance',
                'loanable' => false,
                'state' => Maintenance::class,
            ]);

            expect($this->equipment->fresh()->status)->toBe('maintenance')
                ->and($this->equipment->fresh()->loanable)->toBeFalse()
                ->and($this->equipment->fresh()->is_available)->toBeFalse()
                ->and($this->equipment->fresh()->state)->toBeInstanceOf(Maintenance::class);

            // === PHASE 3: ASSIGN AND START WORK ===
            $damageReport->assignTo($this->technician);
            $damageReport->markStarted();

            expect($damageReport->fresh()->assigned_to_id)->toBe($this->technician->id)
                ->and($damageReport->fresh()->status)->toBe('in_progress')
                ->and($damageReport->fresh()->started_at)->not->toBeNull();

            // === PHASE 4: COMPLETE REPAIR ===
            $damageReport->markCompleted(
                'Adjusted and tightened all tuning pegs. Guitar holds tune properly.',
                45
            );

            expect($damageReport->fresh()->status)->toBe('completed')
                ->and($damageReport->fresh()->completed_at)->not->toBeNull()
                ->and($damageReport->fresh()->repair_notes)->toContain('tightened')
                ->and($damageReport->fresh()->actual_cost)->toBe(45)
                ->and($damageReport->fresh()->is_open)->toBeFalse();

            // Equipment condition updated after repair
            expect($this->equipment->fresh()->condition)->toBe('good');

            // === PHASE 5: RETURN TO SERVICE ===
            $this->equipment->update([
                'status' => 'available',
                'loanable' => true,
                'state' => Available::class,
            ]);

            expect($this->equipment->fresh()->status)->toBe('available')
                ->and($this->equipment->fresh()->loanable)->toBeTrue()
                ->and($this->equipment->fresh()->is_available)->toBeTrue()
                ->and($this->equipment->fresh()->state)->toBeInstanceOf(Available::class);
        });
    });

    describe('Maintenance Mode Equipment Behavior', function () {
        it('correctly handles equipment availability during maintenance', function () {
            // === INITIAL STATE: EQUIPMENT AVAILABLE ===
            expect($this->equipment->is_available)->toBeTrue()
                ->and($this->equipment->loanable)->toBeTrue()
                ->and($this->equipment->status)->toBe('available');

            // === MOVE TO MAINTENANCE MODE ===
            $this->equipment->update([
                'status' => 'maintenance',
                'loanable' => false,
                'state' => Maintenance::class,
            ]);

            // === VERIFY MAINTENANCE STATE ===
            expect($this->equipment->fresh()->is_available)->toBeFalse()
                ->and($this->equipment->fresh()->loanable)->toBeFalse()
                ->and($this->equipment->fresh()->status)->toBe('maintenance')
                ->and($this->equipment->fresh()->state)->toBeInstanceOf(Maintenance::class);

            // === CHECK SCOPES AND QUERIES ===
            $availableEquipment = Equipment::available()->get();
            expect($availableEquipment->contains($this->equipment))->toBeFalse();

            $maintenanceEquipment = Equipment::where('status', 'maintenance')->get();
            expect($maintenanceEquipment->contains($this->equipment->fresh()))->toBeTrue();

            // === RETURN TO SERVICE ===
            $this->equipment->update([
                'status' => 'available',
                'loanable' => true,
                'state' => Available::class,
            ]);

            expect($this->equipment->fresh()->is_available)->toBeTrue();

            $availableAgain = Equipment::available()->get();
            expect($availableAgain->contains($this->equipment->fresh()))->toBeTrue();
        });
    });

    describe('Multiple Damage Reports Per Equipment', function () {
        it('tracks multiple damage reports and their outcomes', function () {
            // === CREATE MULTIPLE DAMAGE REPORTS ===
            $report1 = EquipmentDamageReport::factory()->create([
                'equipment_id' => $this->equipment->id,
                'title' => 'Cosmetic damage',
                'severity' => 'low',
                'priority' => 'normal', // Explicitly not high priority
                'status' => 'completed',
                'actual_cost' => 25,
            ]);

            $report2 = EquipmentDamageReport::factory()->create([
                'equipment_id' => $this->equipment->id,
                'title' => 'Hardware issue',
                'severity' => 'medium',
                'priority' => 'normal', // Explicitly not high priority
                'status' => 'completed',
                'actual_cost' => 75,
            ]);

            $report3 = EquipmentDamageReport::factory()->create([
                'equipment_id' => $this->equipment->id,
                'title' => 'Current issue',
                'severity' => 'high', // This makes it high priority
                'priority' => 'normal', // Even with normal priority, high severity = high priority
                'status' => 'in_progress',
                'estimated_cost' => 100,
            ]);

            // === VERIFY DAMAGE REPORT RELATIONSHIPS ===
            $allReports = $this->equipment->damageReports;
            expect($allReports)->toHaveCount(3);

            $openReports = $this->equipment->openDamageReports;
            expect($openReports)->toHaveCount(1)
                ->and($openReports->first()->title)->toBe('Current issue');

            // === VERIFY COST CALCULATIONS ===
            $completedReports = $allReports->where('status', 'completed');
            $totalCompletedCosts = $completedReports->sum('actual_cost');
            expect($totalCompletedCosts)->toBe(100); // $25 + $75

            $pendingCosts = $allReports->where('status', 'in_progress')->sum('estimated_cost');
            expect($pendingCosts)->toBe(100);

            // === VERIFY PRIORITY FILTERING ===
            $highPriorityReports = EquipmentDamageReport::forEquipment($this->equipment)
                ->highPriority()
                ->get();
            expect($highPriorityReports)->toHaveCount(1); // Only the 'high' severity report
        });
    });

    describe('Equipment Retirement Due to Damage', function () {
        it('handles equipment that becomes uneconomical to repair', function () {
            // === HIGH-VALUE DAMAGE REPORT ===
            $criticalReport = EquipmentDamageReport::create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->staff->id,
                'title' => 'Severe structural damage',
                'description' => 'Major damage that would cost more than equipment value to repair',
                'severity' => 'critical',
                'priority' => 'urgent',
                'status' => 'in_progress',
                'discovered_at' => now(),
                'estimated_cost' => 600, // More than equipment's $500 value
            ]);

            expect($criticalReport->is_high_priority)->toBeTrue()
                ->and($criticalReport->severity)->toBe('critical');

            // === ECONOMIC DECISION: RETIRE EQUIPMENT ===
            $this->equipment->update([
                'status' => 'retired',
                'loanable' => false,
                'condition' => 'poor',
                'notes' => 'Retired due to uneconomical repair costs - damage exceeds equipment value',
            ]);

            $criticalReport->update([
                'status' => 'cancelled',
                'repair_notes' => 'Repair deemed uneconomical. Equipment retired from service.',
            ]);

            // === VERIFY RETIREMENT STATUS ===
            expect($this->equipment->fresh()->status)->toBe('retired')
                ->and($this->equipment->fresh()->loanable)->toBeFalse()
                ->and($this->equipment->fresh()->is_available)->toBeFalse()
                ->and($criticalReport->fresh()->status)->toBe('cancelled');

            // === VERIFY EQUIPMENT NO LONGER AVAILABLE ===
            $availableEquipment = Equipment::available()->get();
            expect($availableEquipment->contains($this->equipment))->toBeFalse();

            $retiredEquipment = Equipment::where('status', 'retired')->get();
            expect($retiredEquipment->contains($this->equipment->fresh()))->toBeTrue();
        });
    });

    describe('Preventive Maintenance Workflow', function () {
        it('handles scheduled maintenance without damage reports', function () {
            // === SCHEDULE PREVENTIVE MAINTENANCE ===
            $this->equipment->update([
                'status' => 'maintenance',
                'loanable' => false,
                'notes' => 'Scheduled quarterly maintenance',
                'state' => Maintenance::class,
            ]);

            // === CREATE MAINTENANCE RECORD ===
            $maintenanceRecord = EquipmentDamageReport::create([
                'equipment_id' => $this->equipment->id,
                'equipment_loan_id' => null, // No associated loan for preventive maintenance
                'reported_by_id' => $this->staff->id,
                'assigned_to_id' => $this->technician->id,
                'title' => 'Quarterly preventive maintenance',
                'description' => 'Routine cleaning, inspection, and minor adjustments',
                'severity' => 'low',
                'priority' => 'normal',
                'status' => 'completed',
                'discovered_at' => now(),
                'started_at' => now(),
                'completed_at' => now(),
                'estimated_cost' => 30,
                'actual_cost' => 25,
                'repair_notes' => 'Cleaned, inspected, and adjusted. Equipment in excellent condition.',
            ]);

            expect($maintenanceRecord->loan)->toBeNull()
                ->and($maintenanceRecord->equipment->id)->toBe($this->equipment->id)
                ->and($maintenanceRecord->status)->toBe('completed');

            // === RETURN TO SERVICE AFTER MAINTENANCE ===
            $this->equipment->update([
                'condition' => 'excellent',
                'status' => 'available',
                'loanable' => true,
                'state' => Available::class,
            ]);

            expect($this->equipment->fresh()->condition)->toBe('excellent')
                ->and($this->equipment->fresh()->is_available)->toBeTrue();
        });
    });
});