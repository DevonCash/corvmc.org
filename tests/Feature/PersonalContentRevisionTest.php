<?php

use App\Models\User;
use App\Models\MemberProfile;
use App\Models\Band;
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

describe('Personal Member Content Auto-Approval', function () {
    it('auto-approves member profile updates for members in good standing', function () {
        $user = User::factory()->create([
            'trust_points' => ['App\Models\MemberProfile' => 10, 'global' => 10]
        ]);
        
        $profile = MemberProfile::create([
            'user_id' => $user->id,
            'bio' => 'Original bio content'
        ]);
        
        $this->actingAs($user);
        
        // Update profile - should be auto-approved for personal content
        $result = $profile->update(['bio' => 'Updated personal bio']);
        
        // For personal content, updates should be applied immediately
        $profile->refresh();
        expect($profile->bio)->toBe('Updated personal bio');
        
        // Should create a revision record showing auto-approval
        $revision = Revision::where('revisionable_id', $profile->id)
                          ->where('revisionable_type', MemberProfile::class)
                          ->first();
        
        expect($revision)->not->toBeNull();
        expect($revision->status)->toBe(Revision::STATUS_APPROVED);
        expect($revision->auto_approved)->toBeTrue();
    })->group('personal-content', 'update-my-member-profile-freely');
    
    it('auto-approves band profile updates for owners in good standing', function () {
        $user = User::factory()->create([
            'trust_points' => ['App\Models\Band' => 10, 'global' => 10]
        ]);
        
        $band = Band::factory()->create(['owner_id' => $user->id]);
        
        $this->actingAs($user);
        
        // Update band profile - should be auto-approved for band owners
        $originalBio = $band->bio;
        $newBio = 'Updated band information';
        
        $result = $band->update(['bio' => $newBio]);
        
        // For personal band content, updates should be applied immediately
        $band->refresh();
        expect($band->bio)->toBe($newBio);
        
        // Should create a revision record showing auto-approval
        $revision = Revision::where('revisionable_id', $band->id)
                          ->where('revisionable_type', Band::class)
                          ->first();
        
        expect($revision)->not->toBeNull();
        expect($revision->status)->toBe(Revision::STATUS_APPROVED);
        expect($revision->auto_approved)->toBeTrue();
    })->group('personal-content', 'update-my-band-profile-as-owner');
    
    it('requires approval for member profile updates when user has poor standing', function () {
        // User with poor trust standing (negative points indicate violations)
        $user = User::factory()->create([
            'trust_points' => ['App\Models\MemberProfile' => -10, 'global' => -10]
        ]);
        
        $profile = MemberProfile::create([
            'user_id' => $user->id,
            'bio' => 'Original bio content'
        ]);
        
        $this->actingAs($user);
        
        // Update should create pending revision due to poor standing
        $profile->update(['bio' => 'Updated bio from user in poor standing']);
        
        // Original content should remain unchanged
        $profile->refresh();
        expect($profile->bio)->toBe('Original bio content');
        
        // Should create pending revision
        $revision = Revision::where('revisionable_id', $profile->id)
                          ->where('revisionable_type', MemberProfile::class)
                          ->first();
        
        expect($revision)->not->toBeNull();
        expect($revision->status)->toBe(Revision::STATUS_PENDING);
        expect($revision->auto_approved)->toBeFalse();
        expect($revision->proposed_changes['bio'])->toBe('Updated bio from user in poor standing');
    })->group('personal-content', 'maintain-personal-content-despite-poor-standing');
    
    it('shows clear notification when trust standing affects auto-approval', function () {
        $user = User::factory()->create([
            'trust_points' => ['App\Models\MemberProfile' => -6, 'global' => -6]
        ]);
        
        $profile = MemberProfile::create([
            'user_id' => $user->id,
            'bio' => 'Original bio'
        ]);
        
        $this->actingAs($user);
        
        // Check revision workflow shows approval is required due to poor standing
        $workflow = $profile->getRevisionWorkflow();
        
        expect($workflow['requires_approval'])->toBeTrue();
        expect($workflow['reason'])->toContain('Poor standing');
    })->group('personal-content', 'maintain-personal-content-despite-poor-standing');
});

describe('Personal Content Longitudinal Behavior', function () {
    it('maintains auto-approval as user builds positive standing', function () {
        $user = User::factory()->create([
            'trust_points' => ['App\Models\MemberProfile' => 5, 'global' => 5]
        ]);
        
        $profile = MemberProfile::create([
            'user_id' => $user->id,
            'bio' => 'Original bio'
        ]);
        
        $this->actingAs($user);
        
        // Multiple updates should all be auto-approved for personal content
        for ($i = 1; $i <= 5; $i++) {
            $profile->update(['bio' => "Updated bio version {$i}"]);
            
            $profile->refresh();
            expect($profile->bio)->toBe("Updated bio version {$i}");
            
            // Each should create an approved revision
            $latestRevision = $profile->revisions()->latest()->first();
            expect($latestRevision->status)->toBe(Revision::STATUS_APPROVED);
            expect($latestRevision->auto_approved)->toBeTrue();
        }
        
        // Should have 5 approved revisions
        expect($profile->revisions()->approved()->count())->toBe(5);
    })->group('personal-content', 'update-my-member-profile-freely');
    
    it('tracks recovery from poor standing to good standing', function () {
        // Start with poor standing
        $user = User::factory()->create([
            'trust_points' => ['App\Models\MemberProfile' => -10, 'global' => -10]
        ]);
        
        $profile = MemberProfile::create([
            'user_id' => $user->id,
            'bio' => 'Original bio'
        ]);
        
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');
        
        $this->actingAs($user);
        
        // First update should require approval
        $profile->update(['bio' => 'First update']);
        $revision1 = $profile->revisions()->latest()->first();
        expect($revision1->status)->toBe(Revision::STATUS_PENDING);
        
        // Moderator approves, improving trust
        $this->revisionService->approveRevision($revision1, $moderator, 'Good improvement');
        
        // Improve user's standing significantly
        $user->update(['trust_points' => ['App\Models\MemberProfile' => 15, 'global' => 15]]);
        
        // Re-fetch user to ensure fresh data
        $user = User::find($user->id);
        $this->actingAs($user);
        
        // Next update should be auto-approved due to improved standing
        $profile->update(['bio' => 'Second update after improvement']);
        
        $profile->refresh();
        expect($profile->bio)->toBe('Second update after improvement');
        
        $revision2 = $profile->revisions()->latest()->first();
        expect($revision2->status)->toBe(Revision::STATUS_APPROVED);
        expect($revision2->auto_approved)->toBeTrue();
    })->group('personal-content', 'maintain-personal-content-despite-poor-standing');
});

describe('Band Profile Owner Privileges', function () {
    it('allows immediate updates for band owners regardless of band members', function () {
        $owner = User::factory()->create([
            'trust_points' => ['App\Models\Band' => 10, 'global' => 10]
        ]);
        
        $member = User::factory()->create([
            'trust_points' => ['App\Models\Band' => 0, 'global' => 0]
        ]);
        
        $band = Band::factory()->create(['owner_id' => $owner->id]);
        
        // Add member to band
        $band->members()->attach($member->id, ['role' => 'member']);
        
        $this->actingAs($owner);
        
        // Owner updates should be immediate
        $band->update(['bio' => 'Updated by owner']);
        
        $band->refresh();
        expect($band->bio)->toBe('Updated by owner');
        
        // Should be auto-approved
        $revision = $band->revisions()->latest()->first();
        expect($revision->status)->toBe(Revision::STATUS_APPROVED);
        expect($revision->auto_approved)->toBeTrue();
    })->group('personal-content', 'update-my-band-profile-as-owner');
    
    it('prevents non-owners from making direct band updates', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create([
            'trust_points' => ['App\Models\Band' => 0, 'global' => 0]
        ]);
        
        $band = Band::factory()->create(['owner_id' => $owner->id]);
        $band->members()->attach($member->id, ['role' => 'member']);
        
        $this->actingAs($member);
        
        // Member updates should create pending revision
        $originalBio = $band->bio;
        $band->update(['bio' => 'Updated by member']);
        
        // Original should remain unchanged
        $band->refresh();
        expect($band->bio)->toBe($originalBio);
        
        // Should create pending revision
        $revision = $band->revisions()->latest()->first();
        expect($revision->status)->toBe(Revision::STATUS_PENDING);
        expect($revision->submitted_by_id)->toBe($member->id);
    })->group('personal-content', 'update-my-band-profile-as-owner');
});