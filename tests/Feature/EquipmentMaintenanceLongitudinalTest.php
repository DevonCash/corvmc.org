<?php

use App\Models\Equipment;
use App\Models\EquipmentDamageReport;
use App\Models\EquipmentLoan;
use App\Models\User;
use App\Services\EquipmentService;
use App\States\Equipment\Available;
use App\States\Equipment\Maintenance;
use App\States\EquipmentLoan\CheckedOut;
use App\States\EquipmentLoan\Returned;
use Carbon\Carbon;

describe('Equipment Maintenance Longitudinal Workflow Tests', function () {
    beforeEach(function () {
        $this->equipmentService = app(EquipmentService::class);

        // Create test users
        $this->member = User::factory()->create(['name' => 'Equipment Borrower']);
        $this->staff = User::factory()->create(['name' => 'CMC Staff']);
        $this->technician = User::factory()->create(['name' => 'Tech Specialist']);

        // Create equipment in good condition
        $this->equipment = Equipment::factory()->create([
            'name' => 'Fender Telecaster Electric Guitar',
            'type' => 'guitar',
            'condition' => 'excellent',
            'status' => 'available',
            'loanable' => true,
            'estimated_value' => 800.00,
            'state' => Available::class,
        ]);
    });

    describe('Complete Damage Discovery to Repair Workflow', function () {
        it('follows equipment from damage discovery through repair completion', function () {
            // === PHASE 1: EQUIPMENT CHECKOUT IN GOOD CONDITION ===
            $initialLoan = $this->equipmentService->checkoutToMember(
                $this->equipment,
                $this->member,
                now()->addWeeks(2),
                'excellent', // condition out
                50.00,
                25.00,
                'Weekend gig borrowing'
            );

            expect($initialLoan->condition_out)->toBe('excellent')
                ->and($this->equipment->fresh()->status)->toBe('checked_out')
                ->and($this->equipment->fresh()->condition)->toBe('excellent');

            // === PHASE 2: DAMAGE DISCOVERED DURING RETURN ===
            $returnedLoan = $this->equipmentService->processReturn(
                $initialLoan,
                'fair', // condition degraded
                'Significant scratches on body, one tuning peg is loose'
            );

            expect($returnedLoan->condition_in)->toBe('fair')
                ->and($returnedLoan->damage_notes)->toContain('scratches')
                ->and($returnedLoan->damage_notes)->toContain('loose')
                ->and($this->equipment->fresh()->condition)->toBe('fair')
                ->and($this->equipment->fresh()->status)->toBe('available'); // Initially back to available

            // === PHASE 3: DAMAGE REPORT CREATION ===
            $damageReport = EquipmentDamageReport::create([
                'equipment_id' => $this->equipment->id,
                'equipment_loan_id' => $returnedLoan->id,
                'reported_by_id' => $this->staff->id,
                'title' => 'Body damage and hardware issues',
                'description' => 'Multiple scratches on guitar body and loose tuning peg affecting playability',
                'severity' => 'medium',
                'priority' => 'high',
                'status' => 'reported',
                'discovered_at' => now(),
                'estimated_cost' => 75, // $75 repair estimate
            ]);

            expect($damageReport->equipment->id)->toBe($this->equipment->id)
                ->and($damageReport->loan->id)->toBe($returnedLoan->id)
                ->and($damageReport->is_open)->toBeTrue()
                ->and($damageReport->is_high_priority)->toBeTrue() // High priority
                ->and($damageReport->reportedBy->name)->toBe('CMC Staff');

            // === PHASE 4: EQUIPMENT MOVED TO MAINTENANCE MODE ===
            $this->equipment->update([
                'status' => 'maintenance',
                'loanable' => false, // Staff disables lending while in maintenance
                'state' => Maintenance::class,
            ]);

            expect($this->equipment->fresh()->status)->toBe('maintenance')
                ->and($this->equipment->fresh()->loanable)->toBeFalse()
                ->and($this->equipment->fresh()->is_available)->toBeFalse()
                ->and($this->equipment->fresh()->state)->toBeInstanceOf(Maintenance::class);

            // Verify equipment is no longer available for new loans
            $availableEquipment = $this->equipmentService->getAllAvailable();
            expect($availableEquipment->contains($this->equipment))->toBeFalse();

            // === PHASE 5: DAMAGE REPORT ASSIGNMENT AND WORK START ===
            $damageReport->assignTo($this->technician);
            $damageReport->markStarted();

            expect($damageReport->fresh()->assigned_to_id)->toBe($this->technician->id)
                ->and($damageReport->fresh()->status)->toBe('in_progress')
                ->and($damageReport->fresh()->started_at)->not->toBeNull();

            // === PHASE 6: REPAIR COMPLETION ===
            $damageReport->markCompleted(
                'Buffed out scratches and tightened tuning peg. Guitar is fully functional.',
                65 // Actual cost was $65
            );

            expect($damageReport->fresh()->status)->toBe('completed')
                ->and($damageReport->fresh()->completed_at)->not->toBeNull()
                ->and($damageReport->fresh()->repair_notes)->toContain('Buffed out')
                ->and($damageReport->fresh()->actual_cost)->toBe(65)
                ->and($damageReport->fresh()->is_open)->toBeFalse();

            // Equipment condition automatically updated to 'good' due to successful repair
            expect($this->equipment->fresh()->condition)->toBe('good');

            // === PHASE 7: EQUIPMENT RETURNED TO SERVICE ===
            $this->equipment->update([
                'status' => 'available',
                'loanable' => true,
                'state' => Available::class,
            ]);

            expect($this->equipment->fresh()->status)->toBe('available')
                ->and($this->equipment->fresh()->loanable)->toBeTrue()
                ->and($this->equipment->fresh()->is_available)->toBeTrue()
                ->and($this->equipment->fresh()->state)->toBeInstanceOf(Available::class);

            // Verify equipment is available for lending again
            $availableEquipment = $this->equipmentService->getAllAvailable();
            expect($availableEquipment->contains($this->equipment->fresh()))->toBeTrue();

            // === PHASE 8: POST-REPAIR LENDING SUCCESS ===
            $postRepairLoan = $this->equipmentService->checkoutToMember(
                $this->equipment,
                $this->member,
                now()->addWeeks(1),
                'good', // Now in good condition
                50.00,
                25.00,
                'Post-repair test borrowing'
            );

            expect($postRepairLoan->condition_out)->toBe('good')
                ->and($postRepairLoan->equipment->condition)->toBe('good');

            // === PHASE 9: VERIFY COMPLETE AUDIT TRAIL ===
            // Check loan history shows both pre and post repair loans
            $loanHistory = $this->equipmentService->getLoanHistoryForEquipment($this->equipment);
            expect($loanHistory)->toHaveCount(2)
                ->and($loanHistory->first()->id)->toBe($postRepairLoan->id) // Most recent first
                ->and($loanHistory->last()->id)->toBe($returnedLoan->id);

            // Check damage report is linked to original loan
            $damageReports = $this->equipment->damageReports;
            expect($damageReports)->toHaveCount(1)
                ->and($damageReports->first()->loan->id)->toBe($returnedLoan->id)
                ->and($damageReports->first()->status)->toBe('completed');

            // Verify cost tracking
            expect($damageReport->fresh()->estimated_cost)->toBe(75)
                ->and($damageReport->fresh()->actual_cost)->toBe(65); // Came in under budget
        });
    });

    describe('Preventive Maintenance Workflow', function () {
        it('handles scheduled maintenance without damage reports', function () {
            // === PHASE 1: SCHEDULED MAINTENANCE INITIATION ===
            // Staff schedules preventive maintenance (no damage report needed)
            $this->equipment->update([
                'status' => 'maintenance',
                'loanable' => false,
                'notes' => 'Scheduled 6-month cleaning and setup',
                'state' => Maintenance::class,
            ]);

            expect($this->equipment->fresh()->status)->toBe('maintenance')
                ->and($this->equipment->fresh()->is_available)->toBeFalse();

            // === PHASE 2: CREATE MAINTENANCE RECORD ===
            $maintenanceReport = EquipmentDamageReport::create([
                'equipment_id' => $this->equipment->id,
                'equipment_loan_id' => null, // No associated loan
                'reported_by_id' => $this->staff->id,
                'assigned_to_id' => $this->technician->id,
                'title' => 'Scheduled preventive maintenance',
                'description' => 'Routine cleaning, string replacement, and intonation adjustment',
                'severity' => 'low',
                'priority' => 'normal',
                'status' => 'in_progress',
                'discovered_at' => now(),
                'started_at' => now(),
                'estimated_cost' => 30,
            ]);

            expect($maintenanceReport->loan)->toBeNull() // No loan associated
                ->and($maintenanceReport->equipment->id)->toBe($this->equipment->id);

            // === PHASE 3: MAINTENANCE COMPLETION ===
            $maintenanceReport->markCompleted(
                'Cleaned, restrung, and adjusted intonation. Guitar is in excellent condition.',
                25
            );

            // Update equipment status after maintenance (condition already set by markCompleted)
            $this->equipment->update([
                'status' => 'available',
                'loanable' => true,
                'state' => Available::class,
            ]);

            expect($this->equipment->fresh()->condition)->toBe('good') // Set by markCompleted when actual_cost > 0
                ->and($this->equipment->fresh()->is_available)->toBeTrue()
                ->and($maintenanceReport->fresh()->status)->toBe('completed');
        });
    });

    describe('Critical Damage and Equipment Retirement', function () {
        it('handles equipment that cannot be economically repaired', function () {
            // === PHASE 1: SEVERE DAMAGE DISCOVERY ===
            $loan = $this->equipmentService->checkoutToMember(
                $this->equipment,
                $this->member,
                now()->addWeek()
            );

            // Simulate severe damage during use
            $this->equipmentService->processReturn(
                $loan,
                'poor',
                'Neck cracked, electronics damaged beyond repair'
            );

            // === PHASE 2: CRITICAL DAMAGE REPORT ===
            $criticalReport = EquipmentDamageReport::create([
                'equipment_id' => $this->equipment->id,
                'equipment_loan_id' => $loan->id,
                'reported_by_id' => $this->staff->id,
                'assigned_to_id' => $this->technician->id,
                'title' => 'Severe structural and electronic damage',
                'description' => 'Neck has visible crack, pickups not functioning, bridge damaged',
                'severity' => 'critical',
                'priority' => 'urgent',
                'status' => 'in_progress',
                'discovered_at' => now(),
                'started_at' => now(),
                'estimated_cost' => 500, // More than equipment value
            ]);

            expect($criticalReport->is_high_priority)->toBeTrue()
                ->and($criticalReport->severity)->toBe('critical');

            // === PHASE 3: ECONOMIC ASSESSMENT ===
            // Repair cost exceeds equipment value - mark for retirement
            $this->equipment->update([
                'status' => 'retired',
                'loanable' => false,
                'condition' => 'poor',
                'notes' => 'Retired due to uneconomical repair costs'
            ]);

            $criticalReport->update([
                'status' => 'cancelled',
                'repair_notes' => 'Repair deemed uneconomical. Equipment retired.'
            ]);

            expect($this->equipment->fresh()->status)->toBe('retired')
                ->and($this->equipment->fresh()->is_available)->toBeFalse()
                ->and($criticalReport->fresh()->status)->toBe('cancelled');

            // Verify equipment no longer appears in available equipment
            $availableEquipment = $this->equipmentService->getAllAvailable();
            expect($availableEquipment->contains($this->equipment))->toBeFalse();
        });
    });

    describe('Multiple Damage Reports and Repair History', function () {
        it('tracks multiple damage incidents and repairs over time', function () {
            // === INCIDENT 1: MINOR COSMETIC DAMAGE ===
            // Use past dates to simulate completed loan history
            $pastStart = now()->subDays(30);
            $pastEnd = now()->subDays(23);
            
            $loan1 = EquipmentLoan::factory()->create([
                'equipment_id' => $this->equipment->id,
                'borrower_id' => $this->member->id,
                'state' => \App\States\EquipmentLoan\Returned::class,
                'checked_out_at' => $pastStart,
                'due_at' => $pastEnd,
                'returned_at' => $pastEnd,
                'condition_out' => 'excellent',
                'condition_in' => 'good',
                'damage_notes' => 'Small ding on body',
            ]);

            $report1 = EquipmentDamageReport::create([
                'equipment_id' => $this->equipment->id,
                'equipment_loan_id' => $loan1->id,
                'reported_by_id' => $this->staff->id,
                'title' => 'Minor cosmetic damage',
                'description' => 'Small ding on guitar body',
                'severity' => 'low',
                'priority' => 'low',
                'status' => 'completed',
                'discovered_at' => now()->subWeeks(2),
                'completed_at' => now()->subWeeks(2),
                'actual_cost' => 15,
                'repair_notes' => 'Buffed out ding, barely visible now'
            ]);

            // === INCIDENT 2: HARDWARE ISSUE ===
            // Verify equipment is available for new checkout
            expect($this->equipment->fresh()->is_available)->toBeTrue();
            
            $loan2 = $this->equipmentService->checkoutToMember($this->equipment, $this->member, now()->addDays(14));
            $this->equipmentService->processReturn($loan2, 'fair', 'Output jack is loose');

            $report2 = EquipmentDamageReport::create([
                'equipment_id' => $this->equipment->id,
                'equipment_loan_id' => $loan2->id,
                'reported_by_id' => $this->staff->id,
                'title' => 'Loose output jack',
                'description' => 'Output jack needs tightening, affects signal quality',
                'severity' => 'medium',
                'priority' => 'high',
                'status' => 'completed',
                'discovered_at' => now()->subWeek(),
                'completed_at' => now()->subWeek(),
                'actual_cost' => 25,
                'repair_notes' => 'Tightened jack and cleaned connections'
            ]);

            // === VERIFICATION OF REPAIR HISTORY ===
            $allReports = $this->equipment->damageReports()->orderBy('discovered_at')->get();
            expect($allReports)->toHaveCount(2)
                ->and($allReports->first()->title)->toBe('Minor cosmetic damage')
                ->and($allReports->last()->title)->toBe('Loose output jack');

            // Calculate total repair costs
            $totalRepairCosts = $allReports->sum('actual_cost');
            expect($totalRepairCosts)->toBe(40); // $15 + $25

            // Verify equipment is still functional after multiple repairs
            expect($this->equipment->fresh()->is_available)->toBeTrue()
                ->and($this->equipment->fresh()->condition)->toBe('fair'); // Last returned condition

            // === CURRENT STATUS ASSESSMENT ===
            $openReports = $this->equipment->openDamageReports;
            expect($openReports)->toHaveCount(0); // All reports completed

            // Equipment history shows resilience and good maintenance
            $loanHistory = $this->equipmentService->getLoanHistoryForEquipment($this->equipment);
            expect($loanHistory)->toHaveCount(2)
                ->and($loanHistory->every(fn($loan) => $loan->is_returned))->toBeTrue();
        });
    });

    describe('Equipment Maintenance Statistics and Reporting', function () {
        it('provides comprehensive maintenance metrics and insights', function () {
            // === SETUP: CREATE MAINTENANCE HISTORY ===
            // Create multiple damage reports with different outcomes
            $reports = collect([
                ['severity' => 'low', 'priority' => 'low', 'cost' => 20, 'status' => 'completed'],
                ['severity' => 'medium', 'priority' => 'normal', 'cost' => 75, 'status' => 'completed'],
                ['severity' => 'high', 'priority' => 'high', 'cost' => 150, 'status' => 'completed'],
                ['severity' => 'critical', 'priority' => 'urgent', 'cost' => 0, 'status' => 'cancelled'], // Uneconomical
            ]);

            $createdReports = $reports->map(function ($reportData) {
                return EquipmentDamageReport::factory()->create([
                    'equipment_id' => $this->equipment->id,
                    'severity' => $reportData['severity'],
                    'priority' => $reportData['priority'],
                    'actual_cost' => $reportData['cost'],
                    'status' => $reportData['status'],
                ]);
            });

            // === MAINTENANCE STATISTICS VERIFICATION ===
            $allReports = EquipmentDamageReport::forEquipment($this->equipment)->get();
            expect($allReports)->toHaveCount(4);

            $completedReports = EquipmentDamageReport::forEquipment($this->equipment)
                ->where('status', 'completed')->get();
            expect($completedReports)->toHaveCount(3);

            $totalMaintenanceCosts = $completedReports->sum('actual_cost');
            expect($totalMaintenanceCosts)->toBe(245); // $20 + $75 + $150

            $highPriorityReports = EquipmentDamageReport::forEquipment($this->equipment)
                ->highPriority()->get();
            expect($highPriorityReports)->toHaveCount(2); // high and critical severity

            // === EQUIPMENT MAINTENANCE INSIGHTS ===
            // Check if equipment has exceeded economical repair threshold
            $equipmentValue = $this->equipment->estimated_value; // $800
            $maintenanceRatio = $totalMaintenanceCosts / $equipmentValue;

            expect($maintenanceRatio)->toBeLessThan(0.5); // Still economical (30.6% of value)

            // Verify equipment maintenance history is properly tracked
            expect($this->equipment->damageReports->count())->toBe(4)
                ->and($this->equipment->openDamageReports->count())->toBe(0); // All resolved
        });
    });
});
