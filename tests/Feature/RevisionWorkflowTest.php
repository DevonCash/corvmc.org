<?php

use App\Models\User;
use App\Models\MemberProfile;
use App\Models\Revision;
use App\Services\RevisionService;
use App\Services\TrustService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->revisionService = app(RevisionService::class);
    $this->trustService = app(TrustService::class);
    
    // Create test permissions
    Permission::firstOrCreate(['name' => 'approve_revisions']);
    Permission::firstOrCreate(['name' => 'reject_revisions']);
    Permission::firstOrCreate(['name' => 'view_revisions']);
    
    // Create test roles
    $moderator = Role::firstOrCreate(['name' => 'moderator']);
    $admin = Role::firstOrCreate(['name' => 'admin']);
    
    // Assign permissions to roles
    $moderator->givePermissionTo(['approve_revisions', 'reject_revisions', 'view_revisions']);
    $admin->givePermissionTo(['approve_revisions', 'reject_revisions', 'view_revisions']);
});

describe('Content Revision Submission', function () {
    it('creates revision instead of updating content directly for users in poor standing', function () {
        // Create user with poor trust standing (< -5) to force revision workflow
        $user = User::factory()->create([
            'trust_points' => ['App\Models\MemberProfile' => -10, 'global' => -10]
        ]);
        $profile = MemberProfile::create([
            'user_id' => $user->id,
            'bio' => 'Original bio content'
        ]);
        
        $originalBio = $profile->bio;
        $newBio = 'Updated bio content';
        
        $this->actingAs($user);
        
        // Attempt to update should create revision instead
        $result = $profile->update(['bio' => $newBio]);
        
        // Update should return false (revision created instead)
        expect($result)->toBeFalse();
        
        // Original content should remain unchanged
        $profile->refresh();
        expect($profile->bio)->toBe($originalBio);
        
        // Revision should be created
        $revision = Revision::where('revisionable_id', $profile->id)
                          ->where('revisionable_type', MemberProfile::class)
                          ->first();
        
        expect($revision)->not->toBeNull();
        expect($revision->proposed_changes['bio'])->toBe($newBio);
        expect($revision->status)->toBe(Revision::STATUS_PENDING);
        expect($revision->submitted_by_id)->toBe($user->id);
    })->group('revision-system', 'submit-content-revisions');
    
    it('allows direct updates for system operations when no user is authenticated', function () {
        $user = User::factory()->create();
        $profile = MemberProfile::create([
            'user_id' => $user->id,
            'bio' => 'Original bio content'
        ]);
        
        $newBio = 'System updated bio';
        
        // No authenticated user - should update directly
        $result = $profile->update(['bio' => $newBio]);
        
        expect($result)->toBeTrue();
        
        $profile->refresh();
        expect($profile->bio)->toBe($newBio);
        
        // No revision should be created
        $revisionCount = Revision::where('revisionable_id', $profile->id)->count();
        expect($revisionCount)->toBe(0);
    })->group('revision-system', 'submit-content-revisions');
    
    it('excludes exempt fields from revision requirements', function () {
        $user = User::factory()->create();
        $profile = MemberProfile::create([
            'user_id' => $user->id,
            'bio' => 'Original bio content'
        ]);
        
        $this->actingAs($user);
        
        // Update updated_at (exempt field) - should update directly, not create revision
        $result = $profile->update(['updated_at' => now()->addHour()]);
        
        expect($result)->toBeTrue();
        
        // No revision should be created for exempt field updates
        $revisionCount = Revision::where('revisionable_id', $profile->id)->count();
        expect($revisionCount)->toBe(0);
    })->group('revision-system', 'submit-content-revisions');
    
    it('allows submission reason to be provided with revisions', function () {
        $user = User::factory()->create();
        $profile = MemberProfile::create([
            'user_id' => $user->id,
            'bio' => 'Original bio content'
        ]);
        
        $this->actingAs($user);
        
        // Create revision with reason
        $revision = $profile->createRevision(
            ['bio' => 'Updated bio with reason'],
            $user,
            'Updating bio to reflect recent changes'
        );
        
        expect($revision->submission_reason)->toBe('Updating bio to reflect recent changes');
    })->group('revision-system', 'submit-content-revisions');
});

describe('Trust-Based Auto-Approval', function () {
    it('auto-approves revisions from high-trust users', function () {
        $trustedUser = User::factory()->create([
            'trust_points' => ['App\Models\MemberProfile' => 35, 'global' => 35]
        ]);
        
        $profile = MemberProfile::create([
            'user_id' => $trustedUser->id,
            'bio' => 'Original bio content'
        ]);
        
        $this->actingAs($trustedUser);
        
        $newBio = 'Auto-approved bio update';
        $profile->update(['bio' => $newBio]);
        
        // Should be auto-approved and applied immediately
        $profile->refresh();
        expect($profile->bio)->toBe($newBio);
        
        // Should create approved revision record
        $revision = Revision::where('revisionable_id', $profile->id)->first();
        expect($revision)->not->toBeNull();
        expect($revision->status)->toBe(Revision::STATUS_APPROVED);
        expect($revision->auto_approved)->toBeTrue();
    })->group('revision-system', 'earn-auto-approval-privileges');
    
    it('auto-approves personal content for users in good standing', function () {
        $goodStandingUser = User::factory()->create([
            'trust_points' => ['App\Models\MemberProfile' => 5, 'global' => 5]
        ]);
        
        $profile = MemberProfile::create([
            'user_id' => $goodStandingUser->id,
            'bio' => 'Original bio content'
        ]);
        
        $this->actingAs($goodStandingUser);
        
        $newBio = 'Auto-approved personal content update';
        $profile->update(['bio' => $newBio]);
        
        // Should be auto-approved and applied immediately for personal content
        $profile->refresh();
        expect($profile->bio)->toBe($newBio);
        
        // Should create approved revision record
        $revision = Revision::where('revisionable_id', $profile->id)->first();
        expect($revision->status)->toBe(Revision::STATUS_APPROVED);
        expect($revision->auto_approved)->toBeTrue();
    })->group('revision-system', 'personal-content-auto-approval');
    
    it('awards trust points for approved revisions', function () {
        // Create user with poor standing to force manual approval workflow
        $user = User::factory()->create([
            'trust_points' => ['App\Models\MemberProfile' => -10, 'global' => -10]
        ]);
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');
        
        $profile = MemberProfile::create([
            'user_id' => $user->id,
            'bio' => 'Original bio content'
        ]);
        
        $this->actingAs($user);
        $profile->update(['bio' => 'Updated bio content']);
        
        $revision = Revision::where('revisionable_id', $profile->id)->first();
        expect($revision->status)->toBe(Revision::STATUS_PENDING);
        
        // Approve the revision
        $this->revisionService->approveRevision($revision, $moderator, 'Good update');
        
        // Check trust points were awarded (should improve from -10 to -9)
        $user->refresh();
        $trustPoints = $user->trust_points['App\Models\MemberProfile'] ?? 0;
        expect($trustPoints)->toBe(-9); // Original -10 + 1 for successful revision
    })->group('revision-system', 'build-content-trust-through-quality');
});

describe('Revision Status Tracking', function () {
    it('allows users to view their revision history', function () {
        // Create user with poor standing to force manual approval workflow
        $user = User::factory()->create([
            'trust_points' => ['App\Models\MemberProfile' => -10, 'global' => -10]
        ]);
        $profile = MemberProfile::create([
            'user_id' => $user->id,
            'bio' => 'Original bio content'
        ]);
        
        $this->actingAs($user);
        
        // Create multiple revisions
        $profile->update(['bio' => 'First update']);
        $profile->update(['hometown' => 'New Town']);
        
        $revisions = $profile->revisions;
        expect($revisions)->toHaveCount(2);
        
        foreach ($revisions as $revision) {
            expect($revision->submitted_by_id)->toBe($user->id);
            expect($revision->status)->toBe(Revision::STATUS_PENDING);
        }
    })->group('revision-system', 'track-my-revision-status');
    
    it('provides revision summary information', function () {
        // Create user with poor standing to ensure no auto-approval
        $user = User::factory()->create([
            'trust_points' => ['App\Models\MemberProfile' => -10, 'global' => -10]
        ]);
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');
        
        $profile = MemberProfile::create([
            'user_id' => $user->id,
            'bio' => 'Original bio content'
        ]);
        
        $this->actingAs($user);
        
        // Create and approve one revision
        $profile->update(['bio' => 'First update']);
        $revision1 = $profile->revisions()->first();
        $this->revisionService->approveRevision($revision1, $moderator, 'Approved');
        
        // Create different user for second revision to avoid auto-approval
        $newUser = User::factory()->create([
            'trust_points' => ['App\Models\MemberProfile' => -10, 'global' => -10]
        ]);
        $this->actingAs($newUser);
        
        // Create another profile for the new user
        $profile2 = MemberProfile::create([
            'user_id' => $newUser->id,
            'hometown' => 'Original town'
        ]);
        
        // Create and reject a revision from the new user
        $profile2->update(['hometown' => 'New Town']);
        $revision2 = $profile2->revisions()->latest()->first();
        
        $this->revisionService->rejectRevision($revision2, $moderator, 'Invalid location');
        
        // Switch back to original user and create pending revision
        $this->actingAs($user);
        $profile->update(['bio' => 'Third update']);
        
        // Test summary for original profile (approved + pending)
        $summary = $profile->getRevisionSummary();
        expect($summary['total'])->toBe(2);
        expect($summary['approved'])->toBe(1);
        expect($summary['pending'])->toBe(1);
        
        // Test summary for second profile (rejected)
        $summary2 = $profile2->getRevisionSummary();
        expect($summary2['total'])->toBe(1);
        expect($summary2['rejected'])->toBe(1);
    })->group('revision-system', 'track-my-revision-status');
});

describe('Force Update Capabilities', function () {
    it('allows force updates to bypass revision system', function () {
        $user = User::factory()->create();
        $profile = MemberProfile::create([
            'user_id' => $user->id,
            'bio' => 'Original bio content'
        ]);
        
        $this->actingAs($user);
        
        $newBio = 'Force updated bio';
        $result = $profile->forceUpdate(['bio' => $newBio]);
        
        expect($result)->toBeTrue();
        
        $profile->refresh();
        expect($profile->bio)->toBe($newBio);
        
        // No revision should be created
        $revisionCount = Revision::where('revisionable_id', $profile->id)->count();
        expect($revisionCount)->toBe(0);
    })->group('revision-system', 'handle-edge-cases-and-recovery');
});