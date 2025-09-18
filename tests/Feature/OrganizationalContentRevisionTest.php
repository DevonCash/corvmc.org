<?php

use App\Models\User;
use App\Models\StaffProfile;
use App\Models\Revision;
use App\Services\RevisionService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->revisionService = app(RevisionService::class);
    
    // Create test permissions
    Permission::firstOrCreate(['name' => 'approve_revisions']);
    Permission::firstOrCreate(['name' => 'reject_revisions']);
    Permission::firstOrCreate(['name' => 'view_revisions']);
    Permission::firstOrCreate(['name' => 'manage_staff_profiles']);
    
    // Create test roles
    $moderator = Role::firstOrCreate(['name' => 'moderator']);
    $admin = Role::firstOrCreate(['name' => 'admin']);
    $staff = Role::firstOrCreate(['name' => 'staff']);
    
    // Assign permissions to roles
    $moderator->givePermissionTo(['approve_revisions', 'reject_revisions', 'view_revisions']);
    $admin->givePermissionTo(['approve_revisions', 'reject_revisions', 'view_revisions', 'manage_staff_profiles']);
    $staff->givePermissionTo(['view_revisions']);
});

describe('Staff Profile Update Requirements', function () {
    it('requires approval for all staff profile changes', function () {
        $staffMember = User::factory()->create([
            'trust_points' => ['staff_profiles' => 50, 'global' => 50] // High trust
        ]);
        $staffMember->assignRole('staff');
        
        $staffProfile = StaffProfile::create([
            'user_id' => $staffMember->id,
            'name' => 'Community Coordinator',
            'title' => 'Community Coordinator',
            'bio' => 'Original staff bio',
            'type' => \App\Models\StaffProfileType::Staff,
            'sort_order' => 1,
            'is_active' => true
        ]);
        
        $this->actingAs($staffMember);
        
        // Even with high trust, staff profile updates should require approval
        $staffProfile->update(['bio' => 'Updated staff bio']);
        
        // Original content should remain unchanged
        $staffProfile->refresh();
        expect($staffProfile->bio)->toBe('Original staff bio');
        
        // Should create pending revision regardless of trust level
        $revision = Revision::where('revisionable_id', $staffProfile->id)
                          ->where('revisionable_type', StaffProfile::class)
                          ->first();
        
        expect($revision)->not->toBeNull();
        expect($revision->status)->toBe(Revision::STATUS_PENDING);
        expect($revision->auto_approved)->toBeFalse();
        expect($revision->proposed_changes['bio'])->toBe('Updated staff bio');
    })->group('organizational-content', 'update-staff-profile-as-staff-member');
    
    it('allows staff members to submit changes with explanatory notes', function () {
        $staffMember = User::factory()->create();
        $staffMember->assignRole('staff');
        
        $staffProfile = StaffProfile::create([
            'user_id' => $staffMember->id,
            'name' => 'Program Manager',
            'title' => 'Program Manager',
            'bio' => 'Original bio',
            'type' => \App\Models\StaffProfileType::Staff,
            'sort_order' => 1,
            'is_active' => true
        ]);
        
        $this->actingAs($staffMember);
        
        // Create revision with submission reason
        $revision = $staffProfile->createRevision(
            ['title' => 'Senior Program Manager'],
            $staffMember,
            'Promotion to senior role effective this month'
        );
        
        expect($revision->submission_reason)->toBe('Promotion to senior role effective this month');
        expect($revision->status)->toBe(Revision::STATUS_PENDING);
        expect($revision->proposed_changes['title'])->toBe('Senior Program Manager');
    })->group('organizational-content', 'update-staff-profile-as-staff-member');
    
    it('prioritizes staff profile changes for faster review', function () {
        $staffMember = User::factory()->create();
        $staffMember->assignRole('staff');
        
        $staffProfile = StaffProfile::create([
            'user_id' => $staffMember->id,
            'name' => 'Operations Manager',
            'title' => 'Operations Manager',
            'bio' => 'Original bio',
            'type' => \App\Models\StaffProfileType::Staff,
            'sort_order' => 1,
            'is_active' => true
        ]);
        
        // Check approval workflow for staff profiles
        $workflow = $staffProfile->getRevisionWorkflow();
        
        expect($workflow['requires_approval'])->toBeTrue();
        expect($workflow['review_priority'])->toBe('priority');
        expect($workflow['estimated_review_time'])->toBeLessThan(24); // Should be prioritized
        expect($workflow['content_category'])->toBe('organizational');
    })->group('organizational-content', 'update-staff-profile-as-staff-member');
});

describe('Administrative Review Process', function () {
    it('allows administrators to review staff profile changes', function () {
        $staffMember = User::factory()->create();
        $staffMember->assignRole('staff');
        
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        
        $staffProfile = StaffProfile::create([
            'user_id' => $staffMember->id,
            'name' => 'Community Manager',
            'title' => 'Community Manager',
            'bio' => 'Original bio',
            'type' => \App\Models\StaffProfileType::Staff,
            'sort_order' => 1,
            'is_active' => true
        ]);
        
        $this->actingAs($staffMember);
        
        // Staff member submits change
        $staffProfile->update(['bio' => 'Updated professional bio']);
        
        $revision = $staffProfile->revisions()->latest()->first();
        expect($revision->status)->toBe(Revision::STATUS_PENDING);
        
        // Administrator reviews and approves
        $this->revisionService->approveRevision(
            $revision, 
            $admin, 
            'Approved - maintains professional standards'
        );
        
        // Changes should now be applied
        $staffProfile->refresh();
        expect($staffProfile->bio)->toBe('Updated professional bio');
        
        $revision->refresh();
        expect($revision->status)->toBe(Revision::STATUS_APPROVED);
        expect($revision->reviewed_by_id)->toBe($admin->id);
        expect($revision->review_reason)->toContain('professional standards');
    })->group('organizational-content', 'review-staff-profile-changes-as-administrator');
    
    it('allows administrators to reject changes with detailed feedback', function () {
        $staffMember = User::factory()->create();
        $staffMember->assignRole('staff');
        
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        
        $staffProfile = StaffProfile::create([
            'user_id' => $staffMember->id,
            'name' => 'Events Coordinator',
            'title' => 'Events Coordinator',
            'bio' => 'Professional staff bio',
            'type' => \App\Models\StaffProfileType::Staff,
            'sort_order' => 1,
            'is_active' => true
        ]);
        
        $this->actingAs($staffMember);
        
        // Submit potentially inappropriate change
        $staffProfile->update(['bio' => 'Casual bio with personal opinions']);
        
        $revision = $staffProfile->revisions()->latest()->first();
        
        // Administrator rejects with feedback
        $this->revisionService->rejectRevision(
            $revision,
            $admin,
            'Bio content should maintain professional tone and focus on role responsibilities. Please revise to align with organizational voice guidelines.'
        );
        
        // Original content should remain
        $staffProfile->refresh();
        expect($staffProfile->bio)->toBe('Professional staff bio');
        
        $revision->refresh();
        expect($revision->status)->toBe(Revision::STATUS_REJECTED);
        expect($revision->review_reason)->toContain('professional tone');
        expect($revision->review_reason)->toContain('organizational voice guidelines');
    })->group('organizational-content', 'review-staff-profile-changes-as-administrator');
    
    it('maintains audit trail of all organizational content changes', function () {
        $staffMember = User::factory()->create();
        $staffMember->assignRole('staff');
        
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        
        $staffProfile = StaffProfile::create([
            'user_id' => $staffMember->id,
            'name' => 'Development Director',
            'title' => 'Development Director',
            'bio' => 'Original bio',
            'type' => \App\Models\StaffProfileType::Staff,
            'sort_order' => 1,
            'is_active' => true
        ]);
        
        $this->actingAs($staffMember);
        
        // Multiple revision cycles
        $staffProfile->update(['title' => 'Senior Development Director']);
        $revision1 = $staffProfile->revisions()->latest()->first();
        $this->revisionService->approveRevision($revision1, $admin, 'Title update approved');
        
        $staffProfile->update(['bio' => 'Updated bio content']);
        $revision2 = $staffProfile->revisions()->latest()->first();
        $this->revisionService->rejectRevision($revision2, $admin, 'Needs more detail');
        
        $staffProfile->update(['bio' => 'Comprehensive professional bio']);
        $revision3 = $staffProfile->revisions()->latest()->first();
        $this->revisionService->approveRevision($revision3, $admin, 'Much better');
        
        // Check complete audit trail
        $allRevisions = $staffProfile->revisions()->get();
        expect($allRevisions)->toHaveCount(3);
        
        // Verify status tracking
        expect($allRevisions->where('status', Revision::STATUS_APPROVED)->count())->toBe(2);
        expect($allRevisions->where('status', Revision::STATUS_REJECTED)->count())->toBe(1);
        
        // All should have reviewer information
        foreach ($allRevisions as $revision) {
            expect($revision->reviewed_by_id)->toBe($admin->id);
            expect($revision->review_reason)->not->toBeEmpty();
        }
    })->group('organizational-content', 'maintain-organizational-standards');
});

describe('Organizational Standards Enforcement', function () {
    it('enforces strict approval requirements regardless of user trust', function () {
        // Create highly trusted user who becomes staff
        $trustedStaff = User::factory()->create([
            'trust_points' => ['staff_profiles' => 100, 'global' => 100]
        ]);
        $trustedStaff->assignRole('staff');
        
        $staffProfile = StaffProfile::create([
            'user_id' => $trustedStaff->id,
            'name' => 'Executive Director',
            'title' => 'Executive Director',
            'bio' => 'Leadership bio',
            'type' => \App\Models\StaffProfileType::Board,
            'sort_order' => 1,
            'is_active' => true
        ]);
        
        $this->actingAs($trustedStaff);
        
        // Even with maximum trust, organizational content requires approval
        $staffProfile->update(['bio' => 'Updated executive bio']);
        
        $staffProfile->refresh();
        expect($staffProfile->bio)->toBe('Leadership bio'); // Unchanged
        
        $revision = $staffProfile->revisions()->latest()->first();
        expect($revision->status)->toBe(Revision::STATUS_PENDING);
        expect($revision->auto_approved)->toBeFalse();
    })->group('organizational-content', 'maintain-organizational-standards');
    
    it('handles emergency contact updates with expedited review', function () {
        $staffMember = User::factory()->create();
        $staffMember->assignRole('staff');
        
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        
        $staffProfile = StaffProfile::create([
            'user_id' => $staffMember->id,
            'name' => 'Program Assistant',
            'title' => 'Program Assistant',
            'bio' => 'Program assistant bio',
            'type' => \App\Models\StaffProfileType::Staff,
            'sort_order' => 1,
            'is_active' => true,
            'email' => 'assistant@example.com'
        ]);
        
        $this->actingAs($staffMember);
        
        // Emergency contact update
        $revision = $staffProfile->createRevision(
            ['phone' => '555-0199'],
            $staffMember,
            'URGENT: Emergency contact update needed for on-call schedule'
        );
        
        // Check that urgent submissions get expedited handling
        expect($revision->submission_reason)->toContain('URGENT');
        
        $workflow = $staffProfile->getRevisionWorkflow();
        if ($revision->submission_reason && str_contains(strtoupper($revision->submission_reason), 'URGENT')) {
            expect($workflow['estimated_review_time'])->toBeLessThanOrEqual(12); // Same-day handling
        }
    })->group('organizational-content', 'review-staff-profile-changes-as-administrator');
    
    it('prevents direct updates to organizational content', function () {
        $staffMember = User::factory()->create();
        $staffMember->assignRole('staff');
        
        $staffProfile = StaffProfile::create([
            'user_id' => $staffMember->id,
            'name' => 'Marketing Coordinator',
            'title' => 'Marketing Coordinator',
            'bio' => 'Marketing bio',
            'type' => \App\Models\StaffProfileType::Staff,
            'sort_order' => 1,
            'is_active' => true
        ]);
        
        $this->actingAs($staffMember);
        
        // Multiple update attempts should all require approval
        $updates = [
            ['title' => 'Senior Marketing Coordinator'],
            ['bio' => 'Enhanced marketing bio'],
            ['department' => 'Communications & Marketing']
        ];
        
        foreach ($updates as $update) {
            $originalValue = $staffProfile->{array_keys($update)[0]};
            
            $staffProfile->update($update);
            
            // Content should remain unchanged
            $staffProfile->refresh();
            expect($staffProfile->{array_keys($update)[0]})->toBe($originalValue);
            
            // Should create pending revision
            $revision = $staffProfile->revisions()->latest()->first();
            expect($revision->status)->toBe(Revision::STATUS_PENDING);
        }
        
        // Should have created multiple pending revisions
        expect($staffProfile->pendingRevisions()->count())->toBe(3);
    })->group('organizational-content', 'maintain-organizational-standards');
});

describe('Organizational Content Longitudinal Behavior', function () {
    it('maintains consistent approval requirements over time', function () {
        $staffMember = User::factory()->create();
        $staffMember->assignRole('staff');
        
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        
        $staffProfile = StaffProfile::create([
            'user_id' => $staffMember->id,
            'name' => 'Facilities Manager',
            'title' => 'Facilities Manager',
            'bio' => 'Original bio',
            'type' => \App\Models\StaffProfileType::Staff,
            'sort_order' => 1,
            'is_active' => true
        ]);
        
        $this->actingAs($staffMember);
        
        // Simulate multiple updates over time with consistent approval requirements
        $timePoints = [
            ['bio' => 'Updated bio month 1', 'month' => 1],
            ['title' => 'Senior Facilities Manager', 'month' => 6],
            ['bio' => 'Updated bio with new responsibilities', 'month' => 12]
        ];
        
        foreach ($timePoints as $update) {
            $staffProfile->update(array_slice($update, 0, 1));
            
            $revision = $staffProfile->revisions()->latest()->first();
            
            // Should always require approval regardless of time or history
            expect($revision->status)->toBe(Revision::STATUS_PENDING);
            expect($revision->auto_approved)->toBeFalse();
            
            // Approve the change
            $this->revisionService->approveRevision(
                $revision, 
                $admin, 
                "Approved for month {$update['month']}"
            );
        }
        
        // All revisions should show consistent organizational oversight
        $allRevisions = $staffProfile->revisions()->get();
        expect($allRevisions)->toHaveCount(3);
        
        foreach ($allRevisions as $revision) {
            expect($revision->reviewed_by_id)->toBe($admin->id);
            expect($revision->status)->toBe(Revision::STATUS_APPROVED);
        }
    })->group('organizational-content', 'maintain-organizational-standards');
    
    it('handles staff role transitions with appropriate oversight', function () {
        $staffMember = User::factory()->create();
        $staffMember->assignRole('staff');
        
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        
        $staffProfile = StaffProfile::create([
            'user_id' => $staffMember->id,
            'name' => 'Volunteer Coordinator',
            'title' => 'Volunteer Coordinator',
            'bio' => 'Coordinates volunteer programs',
            'type' => \App\Models\StaffProfileType::Staff,
            'sort_order' => 1,
            'is_active' => true
        ]);
        
        $this->actingAs($staffMember);
        
        // Staff member leaves, profile needs deactivation
        $staffProfile->update([
            'title' => 'Former Volunteer Coordinator',
            'bio' => 'Former staff member - profile archived'
        ]);
        
        $revision = $staffProfile->revisions()->latest()->first();
        expect($revision->status)->toBe(Revision::STATUS_PENDING);
        
        // Admin reviews and handles the transition
        $this->revisionService->approveRevision(
            $revision,
            $admin,
            'Staff transition approved - profile archived appropriately'
        );
        
        // Final state should reflect approved changes
        $staffProfile->refresh();
        expect($staffProfile->title)->toBe('Former Volunteer Coordinator');
        expect($staffProfile->bio)->toContain('archived');
        
        // Audit trail should show proper oversight
        $revision->refresh();
        expect($revision->reviewed_by_id)->toBe($admin->id);
        expect($revision->review_reason)->toContain('transition approved');
    })->group('organizational-content', 'maintain-organizational-standards');
});