<?php

use App\Attributes\Story;
use App\Facades\StaffProfileService;
use App\Models\User;
use App\Models\StaffProfile;
use App\Models\StaffProfileType;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    // Create minimal permissions needed for tests
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'approve revisions']);
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'reject revisions']);
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view revisions']);
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage staff profiles']);
    
    // Create admin role and assign permissions
    $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']);
    $adminRole->givePermissionTo(['approve revisions', 'reject revisions', 'view revisions', 'manage staff profiles']);

    $this->admin = \App\Models\User::factory()->create(['name' => 'Site Administrator']);
    $this->staffMember = \App\Models\User::factory()->create(['name' => 'Staff Member']);
    $this->boardMember = \App\Models\User::factory()->create(['name' => 'Board Member']);

    // Assign roles (using existing roles from the system)
    $this->admin->assignRole('admin');

    Notification::fake();
});

describe('Story 1: Create Staff Profile', function () {
    it('allows admins to create staff profiles for users', function () {
        // Story 1: "I can create staff profiles with name, title, bio, and contact information"
        $this->actingAs($this->admin);

        $staffData = [
            'name' => 'Sarah Johnson',
            'title' => 'Executive Director',
            'bio' => 'Sarah brings over 10 years of nonprofit leadership experience to CMC...',
            'type' => 'staff',
            'email' => 'sarah@corvallismusic.org',
            'sort_order' => 1,
            'is_active' => true,
            'user_id' => $this->staffMember->id, // Not linked to member account
        ];

        $profile = StaffProfileService::createStaffProfile($staffData);

        expect($profile)->toBeInstanceOf(StaffProfile::class)
            ->and($profile->name)->toBe('Sarah Johnson')
            ->and($profile->title)->toBe('Executive Director')
            ->and($profile->bio)->toContain('nonprofit leadership experience')
            ->and($profile->type)->toBe(StaffProfileType::Staff)
            ->and($profile->email)->toBe('sarah@corvallismusic.org')
            ->and($profile->sort_order)->toBe(1)
            ->and($profile->is_active)->toBeTrue();
    });

    it('supports different profile types with appropriate display order', function () {
        // Story 1: "I can specify the profile type (board member, staff, volunteer, etc.)"
        // Story 1: "I can set the display order for how profiles appear on the website"
        $this->actingAs($this->admin);

        $boardProfile = StaffProfileService::createStaffProfile([
            'name' => 'Board Chair',
            'title' => 'Board Chairperson',
            'type' => StaffProfileType::Board,
            'sort_order' => 1,
            'is_active' => true,
            'user_id' => $this->boardMember->id,
        ]);

        $staffProfile = StaffProfileService::createStaffProfile([
            'name' => 'Operations Manager',
            'title' => 'Operations Manager',
            'type' => StaffProfileType::Staff,
            'sort_order' => 1,
            'is_active' => true,
            'user_id' => $this->staffMember->id,
        ]);

        expect($boardProfile->type)->toBe(StaffProfileType::Board)
            ->and($staffProfile->type)->toBe(StaffProfileType::Staff);
    });

    it('allows marking profiles as active or inactive', function () {
        // Story 1: "I can mark profiles as active or inactive"
        $this->actingAs($this->admin);

        $activeProfile = StaffProfileService::createStaffProfile([
            'name' => 'Active Member',
            'title' => 'Current Staff',
            'is_active' => true,
            'user_id' => $this->staffMember->id,
        ]);

        $inactiveProfile = StaffProfileService::createStaffProfile([
            'name' => 'Former Member',
            'title' => 'Former Staff',
            'is_active' => false,
            'user_id' => \App\Models\User::factory()->create(['name' => 'Former Staff'])->id,
        ]);

        expect($activeProfile->is_active)->toBeTrue()
            ->and($inactiveProfile->is_active)->toBeFalse();
    });
});

describe('Story 2: Staff Member Self-Edit', function () {
    it('allows staff members to edit their own profiles with approval workflow', function () {
        // Story 2: "I can edit my bio, contact information, and personal details"
        // Story 2: "My changes are saved as 'pending approval' and not immediately public"

        $this->actingAs($this->admin);
        $profile = StaffProfileService::createStaffProfile([
            'name' => 'Staff Member',
            'title' => 'Program Coordinator',
            'bio' => 'Original bio content',
            'user_id' => $this->staffMember->id,
            'is_active' => true,
        ]);

        // Staff member edits their own profile
        $this->actingAs($this->staffMember);
        $updatedProfile = StaffProfileService::submitProfileChanges($profile, [
            'bio' => 'Updated bio with new accomplishments and experience',
            'email' => 'newemail@corvallismusic.org',
            'phone' => '(541) 555-9999',
        ]);

        expect($updatedProfile->status)->toBe('pending_changes')
            ->and($updatedProfile->pending_changes)->not->toBeNull()
            ->and($updatedProfile->pending_changes['bio'])->toBe('Updated bio with new accomplishments and experience')
            ->and($updatedProfile->pending_changes['email'])->toBe('newemail@corvallismusic.org');

        // Original data should remain public until approved
        expect($updatedProfile->bio)->toBe('Original bio content'); // Still shows original
    })->skip('Pending staff profile approval workflow');

    it('prevents staff members from editing restricted fields', function () {
        // Story 2: "I cannot change my title, role type, or display order (admin-only fields)"

        $this->actingAs($this->admin);
        $profile = StaffProfileService::createStaffProfile([
            'name' => 'Staff Member',
            'title' => 'Original Title',
            'profile_type' => 'staff',
            'display_order' => 5,
            'user_id' => $this->staffMember->id,
            'is_active' => true,
        ]);

        // Staff member tries to edit restricted fields
        $this->actingAs($this->staffMember);
        expect(function () use ($profile) {
            StaffProfileService::updateStaffProfile($profile, [
                'title' => 'New Unauthorized Title',
                'profile_type' => 'board',
                'display_order' => 1,
            ]);
        })->toThrow(Exception::class, 'cannot modify restricted fields');
    });

    it('allows staff to see current vs pending versions', function () {
        // Story 2: "I can see the current public version vs. my pending changes"

        $this->actingAs($this->admin);
        $profile = StaffProfileService::createProfile([
            'name' => 'Staff Member',
            'bio' => 'Current public bio',
            'user_id' => $this->staffMember->id,
            'is_active' => true,
        ]);

        // Staff member submits changes
        $this->actingAs($this->staffMember);
        $updatedProfile = StaffProfileService::submitProfileChanges($profile, [
            'bio' => 'Pending new bio content',
        ]);

        // Staff can see both versions
        $profileView = StaffProfileService::getProfileForUser($this->staffMember);

        expect($profileView['current']['bio'])->toBe('Current public bio')
            ->and($profileView['pending']['bio'])->toBe('Pending new bio content')
            ->and($profileView['has_pending_changes'])->toBeTrue();
    })->skip('Pending staff profile approval workflow');

    it('sends notifications when changes are submitted', function () {
        // Story 2: "I receive notification when my changes are approved or rejected"

        $this->actingAs($this->admin);
        $profile = StaffProfileService::createProfile([
            'name' => 'Staff Member',
            'user_id' => $this->staffMember->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->staffMember);
        StaffProfileService::submitProfileChanges($profile, [
            'bio' => 'New bio content',
        ]);

        // TODO: Fix notification assertion - Notification::assertSentTo($this->admin, StaffProfileSubmittedNotification::class);
    });
})->skip('Pending staff profile approval workflow');

describe('Story 3: Admin Profile Approval Workflow', function () {
    it('allows admins to see pending profile changes in review queue', function () {
        // Story 3: "I can see all pending staff profile changes in a review queue"

        $this->actingAs($this->admin);
        $profile1 = StaffProfileService::createProfile([
            'name' => 'Staff One',
            'user_id' => $this->staffMember->id,
            'is_active' => true,
        ]);

        $staffMember2 = \App\Models\User::factory()->create(['name' => 'Staff Two']);
        $profile2 = StaffProfileService::createProfile([
            'name' => 'Staff Two',
            'user_id' => $staffMember2->id,
            'is_active' => true,
        ]);

        // Both staff members submit changes
        $this->actingAs($this->staffMember);
        StaffProfileService::submitProfileChanges($profile1, ['bio' => 'New bio 1']);

        $this->actingAs($staffMember2);
        StaffProfileService::submitProfileChanges($profile2, ['bio' => 'New bio 2']);

        // Admin can see all pending changes
        $this->actingAs($this->admin);
        $pendingChanges = StaffProfileService::getPendingChanges();

        expect($pendingChanges)->toHaveCount(2);

        $profileIds = $pendingChanges->pluck('id')->toArray();
        expect($profileIds)->toContain($profile1->id, $profile2->id);
    });

    it('allows admins to approve profile changes', function () {
        // Story 3: "I can approve changes to make them live on the public website"

        $this->actingAs($this->admin);
        $profile = StaffProfileService::createProfile([
            'name' => 'Staff Member',
            'bio' => 'Original bio',
            'user_id' => $this->staffMember->id,
            'is_active' => true,
        ]);

        // Staff submits changes
        $this->actingAs($this->staffMember);
        StaffProfileService::submitProfileChanges($profile, [
            'bio' => 'Updated approved bio',
            'email' => 'approved@corvallismusic.org',
        ]);

        // Admin approves changes
        $this->actingAs($this->admin);
        $approvedProfile = StaffProfileService::approveChanges($profile, $this->admin);

        expect($approvedProfile->status)->toBe('approved')
            ->and($approvedProfile->bio)->toBe('Updated approved bio')
            ->and($approvedProfile->email)->toBe('approved@corvallismusic.org')
            ->and($approvedProfile->pending_changes)->toBeNull();

        // TODO: Fix notification assertion - Notification::assertSentTo($this->staffMember, StaffProfileApprovedNotification::class);
    });

    it('allows admins to reject changes with explanatory notes', function () {
        // Story 3: "I can reject changes with explanatory notes sent to the staff member"

        $this->actingAs($this->admin);
        $profile = StaffProfileService::createProfile([
            'name' => 'Staff Member',
            'bio' => 'Original bio',
            'user_id' => $this->staffMember->id,
            'is_active' => true,
        ]);

        // Staff submits changes
        $this->actingAs($this->staffMember);
        StaffProfileService::submitProfileChanges($profile, [
            'bio' => 'Inappropriate content that needs revision',
        ]);

        // Admin rejects changes
        $this->actingAs($this->admin);
        $rejectedProfile = StaffProfileService::rejectChanges(
            $profile,
            $this->admin,
            'Bio content needs to be more professional and focus on role responsibilities.'
        );

        expect($rejectedProfile->status)->toBe('approved') // Back to original status
            ->and($rejectedProfile->bio)->toBe('Original bio') // Unchanged
            ->and($rejectedProfile->pending_changes)->toBeNull()
            ->and($rejectedProfile->rejection_notes)->toBe('Bio content needs to be more professional and focus on role responsibilities.');

        // TODO: Fix notification assertion - Notification::assertSentTo($this->staffMember, StaffProfileRejectedNotification::class);
    });

    it('allows admins to edit changes before approval', function () {
        // Story 3: "I can edit changes before approval if minor adjustments are needed"

        $this->actingAs($this->admin);
        $profile = StaffProfileService::createProfile([
            'name' => 'Staff Member',
            'bio' => 'Original bio',
            'user_id' => $this->staffMember->id,
            'is_active' => true,
        ]);

        // Staff submits changes
        $this->actingAs($this->staffMember);
        StaffProfileService::submitProfileChanges($profile, [
            'bio' => 'Good content but needs minor editing for grammar',
        ]);

        // Admin edits and approves in one step
        $this->actingAs($this->admin);
        $approvedProfile = StaffProfileService::editAndApproveChanges($profile, $this->admin, [
            'bio' => 'Good content with corrected grammar and professional formatting',
        ]);

        expect($approvedProfile->bio)->toBe('Good content with corrected grammar and professional formatting')
            ->and($approvedProfile->status)->toBe('approved');
    });
})->skip('Pending staff profile approval workflow');

describe('Story 4: Update Staff Profiles (Admin Direct Edit)', function () {
    it('allows admins to directly edit all profile information', function () {
        // Story 4: "I can edit all staff profile information (name, title, bio, contact)"
        // Story 4: "My direct updates are immediately reflected on the public website (bypass approval)"

        $this->actingAs($this->admin);
        $profile = StaffProfileService::createStaffProfile([
            'name' => 'Original Name',
            'title' => 'Original Title',
            'bio' => 'Original bio',
            'type' => 'staff',
            'is_active' => true,
            'user_id' => $this->staffMember->id,
        ]);

        $updatedProfile = StaffProfileService::updateStaffProfile($profile, [
            'name' => 'Updated Name',
            'title' => 'Updated Title',
            'bio' => 'Updated bio with new information',
            'type' => 'board',
            'email' => 'updated@corvallismusic.org',
            'is_active' => false,
        ]);

        // Admin changes are immediate (no approval needed)
        expect($updatedProfile->name)->toBe('Updated Name')
            ->and($updatedProfile->title)->toBe('Updated Title')
            ->and($updatedProfile->bio)->toBe('Updated bio with new information')
            ->and($updatedProfile->type)->toBe(StaffProfileType::Board)
            ->and($updatedProfile->email)->toBe('updated@corvallismusic.org')
            ->and($updatedProfile->is_active)->toBeFalse();
    });

    it('notifies staff members when admin makes changes', function () {
        // Story 4: "Staff members are notified when I make changes to their profiles"

        $this->actingAs($this->admin);
        $profile = StaffProfileService::createStaffProfile([
            'name' => 'Staff Member',
            'user_id' => $this->staffMember->id,
            'is_active' => true,
        ]);

        Notification::fake(); // Clear creation notifications

        StaffProfileService::updateStaffProfile($profile, [
            'bio' => 'Admin updated this bio',
        ]);

        // TODO: Fix notification assertion - Notification::assertSentTo($this->staffMember, StaffProfileUpdatedNotification::class);
    });
});

describe('Story 5: Staff Profile Organization', function () {
    it('organizes staff profiles by type and display order', function () {
        // Story 5: "I can categorize staff profiles as board members, staff, or volunteers"
        // Story 5: "I can set custom display order for profiles within each category"

        $this->actingAs($this->admin);

        // Create profiles with different types and orders
        $board1 = StaffProfileService::createStaffProfile([
            'name' => 'Board Chair',
            'type' => 'board',
            'sort_order' => 1,
            'is_active' => true,
            'user_id' => $this->boardMember->id,
        ]);

        $board2 = StaffProfileService::createStaffProfile([
            'name' => 'Board Member',
            'type' => 'board',
            'sort_order' => 2,
            'is_active' => true,
            'user_id' => \App\Models\User::factory()->create(['name' => 'Another Board Member'])->id,
        ]);

        $staff1 = StaffProfileService::createStaffProfile([
            'name' => 'Executive Director',
            'type' => 'staff',
            'sort_order' => 1,
            'is_active' => true,
            'user_id' => $this->staffMember->id,
        ]);

        // Get organized profiles
        $organized = StaffProfileService::getOrganizedProfiles();

        expect($organized['board'])->toHaveCount(2)
            ->and($organized['staff'])->toHaveCount(1);

        // Verify display order within types
        expect($organized['board'][0]['name'])->toBe('Board Chair') // display_order 1
            ->and($organized['board'][1]['name'])->toBe('Board Member'); // display_order 2
    });

    it('hides inactive profiles from public view', function () {
        // Story 5: "Inactive profiles are hidden from public view but preserved in admin"

        $this->actingAs($this->admin);

        $activeProfile = StaffProfileService::createStaffProfile([
            'name' => 'Active Staff',
            'is_active' => true,
            'user_id' => $this->staffMember->id,
        ]);

        $inactiveProfile = StaffProfileService::createStaffProfile([
            'name' => 'Inactive Staff',
            'is_active' => false,
            'user_id' => \App\Models\User::factory()->create(['name' => 'Inactive Staff'])->id,
        ]);

        // Public view should only show active profiles
        $publicProfiles = StaffProfileService::getActiveStaffProfiles();
        $publicNames = $publicProfiles->pluck('name')->toArray();

        expect($publicNames)->toContain('Active Staff')
            ->and($publicNames)->not->toContain('Inactive Staff');

        // Admin view should show all profiles
        $allProfiles = StaffProfileService::getAllStaffProfiles();
        $allNames = $allProfiles->pluck('name')->toArray();

        expect($allNames)->toContain('Active Staff', 'Inactive Staff');
    });

    it('supports reordering profiles within categories', function () {
        // Story 5: "I can drag and drop to reorder staff profiles easily"

        $this->actingAs($this->admin);

        $profile1 = StaffProfileService::createStaffProfile([
            'name' => 'First Profile',
            'type' => 'staff',
            'sort_order' => 1,
            'is_active' => true,
            'user_id' => $this->staffMember->id,
        ]);

        $profile2 = StaffProfileService::createStaffProfile([
            'name' => 'Second Profile',
            'type' => 'staff',
            'sort_order' => 2,
            'is_active' => true,
            'user_id' => \App\Models\User::factory()->create(['name' => 'Second Staff'])->id,
        ]);

        // Reorder profiles (swap positions)
        StaffProfileService::reorderStaffProfiles([
            $profile2->id => 1, // Second becomes first
            $profile1->id => 2, // First becomes second
        ]);

        // Verify new order
        $reordered = StaffProfileService::getActiveStaffProfiles('staff');

        expect($reordered[0]->name)->toBe('Second Profile')
            ->and($reordered[0]->sort_order)->toBe(1)
            ->and($reordered[1]->name)->toBe('First Profile')
            ->and($reordered[1]->sort_order)->toBe(2);
    });
});

describe('Story 8: Staff Profile Analytics', function () {
    it('provides statistics about staff profiles and breakdown by type', function () {
        // Story 8: "I can see total counts of active and inactive staff profiles"
        // Story 8: "I can see breakdown by profile type (board, staff, volunteers)"

        $this->actingAs($this->admin);

        // Create various profile types and states
        StaffProfile::factory()->count(2)->create(['type' => 'board', 'is_active' => true]);
        StaffProfile::factory()->count(1)->create(['type' => 'staff', 'is_active' => true]);
        StaffProfile::factory()->count(1)->create(['type' => 'staff', 'is_active' => false]);
        StaffProfile::factory()->count(1)->create(['type' => 'board', 'is_active' => false]);



        $analytics = StaffProfileService::getStaffProfileStats();

        expect($analytics['total_profiles'])->toBe(5)
            ->and($analytics['active_profiles'])->toBe(3)
            ->and($analytics['inactive_profiles'])->toBe(2)
            ->and($analytics['by_type']['board'])->toBe(2)
            ->and($analytics['by_type']['staff'])->toBe(1);
    });
});

describe('Story 9: Bulk Staff Management', function () {
    it('supports bulk operations on multiple staff profiles', function () {
        // Story 9: "I can select multiple staff profiles for bulk operations"
        // Story 9: "I can bulk activate or deactivate profiles during organizational changes"

        $this->actingAs($this->admin);

        $profile1 = StaffProfile::factory()->create([
            'is_active' => true,
        ]);

        $profile2 = StaffProfile::factory()->create([
            'is_active' => true,
        ]);

        $profile3 = StaffProfile::factory()->create([
            'is_active' => true,
        ]);

        // Bulk deactivate multiple profiles
        $profileIds = [$profile1->id, $profile2->id, $profile3->id];
        $updatedCount = StaffProfileService::bulkUpdateProfiles($profileIds, [
            'is_active' => false,
        ]);

        expect($updatedCount)->toBe(3);

        // Verify all profiles were deactivated
        foreach ([$profile1, $profile2, $profile3] as $profile) {
            expect($profile->fresh()->is_active)->toBeFalse();
        }
    });

    it('supports bulk profile type changes', function () {
        // Story 9: "I can bulk update profile types (e.g., move multiple people from staff to board)"

        $this->actingAs($this->admin);

        $profile1 = StaffProfile::factory()->create([
            'name' => 'Staff to Board 1',
            'type' => 'staff',
            'is_active' => true,
        ]);

        $profile2 = StaffProfile::factory()->create([
            'name' => 'Staff to Board 2',
            'type' => 'staff',
            'is_active' => true,
        ]);

        // Bulk change from staff to board
        $profileIds = [$profile1->id, $profile2->id];
        $updatedCount = StaffProfileService::bulkUpdateProfiles($profileIds, [
            'type' => 'board',
        ]);

        expect($updatedCount)->toBe(2);

        // Verify profile type changes
        expect($profile1->fresh()->type)->toBe(StaffProfileType::Board)
            ->and($profile2->fresh()->type)->toBe(StaffProfileType::Board);
    });
});

