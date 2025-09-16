<?php

use App\Models\Equipment;
use App\Models\EquipmentDamageReport;
use App\Models\EquipmentLoan;
use App\Models\User;

describe('Equipment Damage Report Workflow', function () {
    beforeEach(function () {
        $this->equipment = Equipment::factory()->create([
            'name' => 'Test Guitar',
            'condition' => 'good',
        ]);
        
        $this->member = User::factory()->create(['name' => 'Test Member']);
        $this->staff = User::factory()->create(['name' => 'Test Staff']);
        $this->technician = User::factory()->create(['name' => 'Test Technician']);
    });

    describe('Damage Report Creation', function () {
        it('creates a damage report with required fields', function () {
            $report = EquipmentDamageReport::factory()->create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->member->id,
                'title' => 'Scratched surface',
                'description' => 'Small scratch on the guitar body',
                'severity' => 'low',
                'priority' => 'normal',
                'status' => 'reported',
                'discovered_at' => now(),
            ]);

            expect($report->equipment_id)->toBe($this->equipment->id);
            expect($report->reported_by_id)->toBe($this->member->id);
            expect($report->title)->toBe('Scratched surface');
            expect($report->severity)->toBe('low');
            expect($report->is_open)->toBeTrue();
        });

        it('can link damage report to equipment loan', function () {
            $loan = EquipmentLoan::factory()->create([
                'equipment_id' => $this->equipment->id,
                'borrower_id' => $this->member->id,
            ]);

            $report = EquipmentDamageReport::factory()->create([
                'equipment_id' => $this->equipment->id,
                'equipment_loan_id' => $loan->id,
                'reported_by_id' => $this->member->id,
                'title' => 'Damage during loan',
            ]);

            expect($report->loan->id)->toBe($loan->id);
            expect($report->loan->borrower->id)->toBe($this->member->id);
        });

        it('can create damage report without loan', function () {
            $report = EquipmentDamageReport::factory()->withoutLoan()->create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->staff->id,
                'title' => 'General maintenance issue',
            ]);

            expect($report->equipment_loan_id)->toBeNull();
            expect($report->loan)->toBeNull();
        });
    });

    describe('Damage Report Assignment', function () {
        it('can assign damage report to staff member', function () {
            $report = EquipmentDamageReport::factory()->create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->member->id,
                'assigned_to_id' => null,
            ]);

            $report->assignTo($this->technician);

            expect($report->fresh()->assigned_to_id)->toBe($this->technician->id);
            expect($report->assignedTo->name)->toBe('Test Technician');
        });

        it('can change assignment between staff members', function () {
            $report = EquipmentDamageReport::factory()->create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->member->id,
                'assigned_to_id' => $this->staff->id,
            ]);

            $report->assignTo($this->technician);

            expect($report->fresh()->assigned_to_id)->toBe($this->technician->id);
        });
    });

    describe('Damage Report Status Management', function () {
        it('starts damage report work', function () {
            $report = EquipmentDamageReport::factory()->reported()->create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->member->id,
            ]);

            expect($report->status)->toBe('reported');
            expect($report->started_at)->toBeNull();

            $report->markStarted($this->technician->id);

            $fresh = $report->fresh();
            expect($fresh->status)->toBe('in_progress');
            expect($fresh->started_at)->not->toBeNull();
            expect($fresh->assigned_to_id)->toBe($this->technician->id);
        });

        it('completes damage report with repair details', function () {
            $report = EquipmentDamageReport::factory()->inProgress()->create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->member->id,
                'assigned_to_id' => $this->technician->id,
            ]);

            $repairNotes = 'Replaced broken tuning peg with OEM part';
            $actualCost = 2550; // $25.50 in cents

            $report->markCompleted($repairNotes, $actualCost);

            $fresh = $report->fresh();
            expect($fresh->status)->toBe('completed');
            expect($fresh->completed_at)->not->toBeNull();
            expect($fresh->repair_notes)->toBe($repairNotes);
            expect($fresh->actual_cost)->toBe($actualCost);
        });

        it('updates equipment condition when repair is completed', function () {
            $this->equipment->update(['condition' => 'fair']);
            
            $report = EquipmentDamageReport::factory()->inProgress()->create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->member->id,
            ]);

            $report->markCompleted('Fixed the issue', 5000); // $50.00 in cents

            expect($this->equipment->fresh()->condition)->toBe('good');
        });
    });

    describe('Priority and Severity Management', function () {
        it('automatically flags high priority reports', function () {
            $criticalReport = EquipmentDamageReport::factory()->create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->member->id,
                'severity' => 'critical',
                'priority' => 'normal',
            ]);

            $urgentReport = EquipmentDamageReport::factory()->create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->member->id,
                'severity' => 'medium',
                'priority' => 'urgent',
            ]);

            expect($criticalReport->is_high_priority)->toBeTrue();
            expect($urgentReport->is_high_priority)->toBeTrue();
        });

        it('updates priority levels', function () {
            $report = EquipmentDamageReport::factory()->create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->member->id,
                'priority' => 'normal',
            ]);

            $report->setPriority('urgent');

            expect($report->fresh()->priority)->toBe('urgent');
            expect($report->is_high_priority)->toBeTrue();
        });
    });

    describe('Damage Report Calculations', function () {
        it('calculates days open correctly for ongoing reports', function () {
            $report = EquipmentDamageReport::factory()->create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->member->id,
                'discovered_at' => now()->subDays(5),
                'completed_at' => null,
            ]);

            expect($report->days_open)->toBe(5);
        });

        it('calculates days open correctly for completed reports', function () {
            $discoveredAt = now()->subDays(10);
            $completedAt = now()->subDays(3);
            
            $report = EquipmentDamageReport::factory()->create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->member->id,
                'discovered_at' => $discoveredAt,
                'completed_at' => $completedAt,
                'status' => 'completed',
            ]);

            expect($report->days_open)->toBe(7); // 10 - 3 = 7 days
        });

        it('provides appropriate badge colors for UI', function () {
            $lowSeverity = EquipmentDamageReport::factory()->create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->member->id,
                'severity' => 'low',
                'priority' => 'normal',
                'status' => 'reported',
            ]);

            expect($lowSeverity->severity_color)->toBe('success');
            expect($lowSeverity->priority_color)->toBe('info');
            expect($lowSeverity->status_color)->toBe('info');
        });
    });

    describe('Equipment Relationship', function () {
        it('tracks all damage reports for equipment', function () {
            $report1 = EquipmentDamageReport::factory()->create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->member->id,
                'status' => 'completed',
            ]);

            $report2 = EquipmentDamageReport::factory()->create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->staff->id,
                'status' => 'in_progress',
            ]);

            expect($this->equipment->damageReports)->toHaveCount(2);
            expect($this->equipment->openDamageReports)->toHaveCount(1);
            expect($this->equipment->openDamageReports->first()->id)->toBe($report2->id);
        });

        it('links damage reports to related loans', function () {
            $loan = EquipmentLoan::factory()->create([
                'equipment_id' => $this->equipment->id,
                'borrower_id' => $this->member->id,
            ]);

            $report = EquipmentDamageReport::factory()->create([
                'equipment_id' => $this->equipment->id,
                'equipment_loan_id' => $loan->id,
                'reported_by_id' => $this->member->id,
                'title' => 'Damage found during return',
            ]);

            expect($report->equipment->id)->toBe($this->equipment->id);
            expect($report->loan->id)->toBe($loan->id);
            expect($report->loan->equipment->id)->toBe($this->equipment->id);
        });
    });

    describe('Query Scopes', function () {
        beforeEach(function () {
            // Create various reports for testing scopes
            $this->openReport = EquipmentDamageReport::factory()->inProgress()->create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->member->id,
                'assigned_to_id' => $this->technician->id,
                'severity' => 'high',
                'priority' => 'urgent',
            ]);

            $this->completedReport = EquipmentDamageReport::factory()->completed()->create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->member->id,
                'assigned_to_id' => $this->technician->id,
                'severity' => 'medium',
                'priority' => 'normal',
            ]);

            $this->unassignedReport = EquipmentDamageReport::factory()->reported()->create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->member->id,
                'assigned_to_id' => null,
                'severity' => 'low',
                'priority' => 'normal', // Explicitly set to ensure not high priority
            ]);
        });

        it('filters open damage reports', function () {
            $openReports = EquipmentDamageReport::open()->get();
            
            expect($openReports)->toHaveCount(2); // inProgress and reported
            expect($openReports->pluck('id')->toArray())->toContain($this->openReport->id, $this->unassignedReport->id);
            expect($openReports->pluck('id')->toArray())->not->toContain($this->completedReport->id);
        });

        it('filters high priority reports', function () {
            // Only check reports for this specific equipment to avoid test pollution
            $highPriorityReports = EquipmentDamageReport::forEquipment($this->equipment)
                ->highPriority()->get();
            
            expect($highPriorityReports)->toHaveCount(1);
            expect($highPriorityReports->first()->id)->toBe($this->openReport->id);
        });

        it('filters assigned and unassigned reports', function () {
            $assignedReports = EquipmentDamageReport::assigned()->get();
            $unassignedReports = EquipmentDamageReport::unassigned()->get();
            
            expect($assignedReports)->toHaveCount(2); // openReport and completedReport
            expect($unassignedReports)->toHaveCount(1);
            expect($unassignedReports->first()->id)->toBe($this->unassignedReport->id);
        });

        it('filters by severity level', function () {
            $highSeverityReports = EquipmentDamageReport::bySeverity('high')->get();
            $lowSeverityReports = EquipmentDamageReport::bySeverity('low')->get();
            
            expect($highSeverityReports)->toHaveCount(1);
            expect($highSeverityReports->first()->id)->toBe($this->openReport->id);
            expect($lowSeverityReports)->toHaveCount(1);
            expect($lowSeverityReports->first()->id)->toBe($this->unassignedReport->id);
        });

        it('filters reports for specific equipment', function () {
            $otherEquipment = Equipment::factory()->create();
            $otherReport = EquipmentDamageReport::factory()->create([
                'equipment_id' => $otherEquipment->id,
                'reported_by_id' => $this->member->id,
            ]);

            $equipmentReports = EquipmentDamageReport::forEquipment($this->equipment)->get();
            
            expect($equipmentReports)->toHaveCount(3);
            expect($equipmentReports->pluck('id')->toArray())->not->toContain($otherReport->id);
        });
    });

    describe('Factory States', function () {
        it('creates reports in different states', function () {
            $reportedReport = EquipmentDamageReport::factory()->reported()->create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->member->id,
            ]);

            $inProgressReport = EquipmentDamageReport::factory()->inProgress()->create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->member->id,
            ]);

            $completedReport = EquipmentDamageReport::factory()->completed()->create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->member->id,
            ]);

            expect($reportedReport->status)->toBe('reported');
            expect($reportedReport->started_at)->toBeNull();

            expect($inProgressReport->status)->toBe('in_progress');
            expect($inProgressReport->started_at)->not->toBeNull();

            expect($completedReport->status)->toBe('completed');
            expect($completedReport->completed_at)->not->toBeNull();
            expect($completedReport->actual_cost)->not->toBeNull();
        });

        it('creates high priority reports', function () {
            $highPriorityReport = EquipmentDamageReport::factory()->highPriority()->create([
                'equipment_id' => $this->equipment->id,
                'reported_by_id' => $this->member->id,
            ]);

            expect($highPriorityReport->is_high_priority)->toBeTrue();
            expect(in_array($highPriorityReport->severity, ['high', 'critical']))->toBeTrue();
            expect(in_array($highPriorityReport->priority, ['high', 'urgent']))->toBeTrue();
        });
    });
});
