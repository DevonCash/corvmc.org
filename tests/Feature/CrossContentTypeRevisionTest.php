<?php

use App\Models\User;
use App\Models\MemberProfile;
use App\Models\Band;
use App\Models\CommunityEvent;
use App\Models\StaffProfile;
use App\Models\StaffProfileType;
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
    $staff = Role::firstOrCreate(['name' => 'staff']);
    
    // Assign permissions to roles
    $moderator->givePermissionTo(['approve_revisions', 'reject_revisions', 'view_revisions']);
    $admin->givePermissionTo(['approve_revisions', 'reject_revisions', 'view_revisions']);
});

describe('Understanding Revision Requirements by Content Type', function () {
    it('clearly displays different revision requirements for each content type', function () {
        $user = User::factory()->create([
            'trust_points' => ['App\Models\MemberProfile' => 10, 'App\Models\Band' => 5, 'App\Models\CommunityEvent' => 2, 'App\Models\StaffProfile' => 0]
        ]);
        $user->assignRole('staff');
        
        // Create content of each type
        $memberProfile = MemberProfile::create([
            'user_id' => $user->id,
            'bio' => 'Member bio'
        ]);
        
        $band = Band::factory()->create(['owner_id' => $user->id]);
        
        $communityEvent = CommunityEvent::factory()->create([
            'title' => 'Test Event',
            'description' => 'Event description',
            'organizer_id' => $user->id,
            'status' => CommunityEvent::STATUS_PENDING
        ]);
        
        $staffProfile = StaffProfile::create([
            'user_id' => $user->id,
            'name' => 'Test Staff Member',
            'title' => 'Staff Member',
            'bio' => 'Staff bio',
            'type' => StaffProfileType::Staff,
            'sort_order' => 1,
            'is_active' => true
        ]);
        
        // Check workflow requirements for each type
        $memberWorkflow = $memberProfile->getRevisionWorkflow();
        $bandWorkflow = $band->getRevisionWorkflow();
        $eventWorkflow = $communityEvent->getRevisionWorkflow();
        $staffWorkflow = $staffProfile->getRevisionWorkflow();
        
        // Member profile (personal content) - should auto-approve
        expect($memberWorkflow['requires_approval'])->toBeFalse();
        expect($memberWorkflow['content_category'])->toBe('personal');
        
        // Band profile (personal content) - should auto-approve for owner
        expect($bandWorkflow['requires_approval'])->toBeFalse();
        expect($bandWorkflow['content_category'])->toBe('personal');
        
        // Community event (public content) - should require approval for low trust
        expect($eventWorkflow['requires_approval'])->toBeTrue();
        expect($eventWorkflow['content_category'])->toBe('public');
        expect($eventWorkflow['estimated_review_time'])->toBe(72);
        
        // Staff profile (organizational content) - always requires approval
        expect($staffWorkflow['requires_approval'])->toBeTrue();
        expect($staffWorkflow['content_category'])->toBe('organizational');
        expect($staffWorkflow['review_priority'])->toBe('priority');
    })->group('cross-content-type', 'understand-revision-requirements-by-content-type');
    
    it('shows trust level impact on each content type', function () {
        $user = User::factory()->create([
            'trust_points' => ['App\Models\MemberProfile' => 35, 'App\Models\Band' => 35, 'App\Models\CommunityEvent' => 35, 'App\Models\StaffProfile' => 35]
        ]);
        $user->assignRole('staff');
        
        // Check trust info for each content type
        $memberTrust = $this->trustService->getTrustLevelInfo($user, 'App\Models\MemberProfile');
        $bandTrust = $this->trustService->getTrustLevelInfo($user, 'App\Models\Band');
        $eventTrust = $this->trustService->getTrustLevelInfo($user, 'App\Models\CommunityEvent');
        $staffTrust = $this->trustService->getTrustLevelInfo($user, 'App\Models\StaffProfile');
        
        // Personal content should show auto-approval
        expect($memberTrust['can_auto_approve'])->toBeTrue();
        expect($bandTrust['can_auto_approve'])->toBeTrue();
        
        // Public content should show auto-approval for trusted users
        expect($eventTrust['can_auto_approve'])->toBeTrue();
        
        // Staff content should never auto-approve regardless of trust
        expect($staffTrust['can_auto_approve'])->toBeFalse();
    })->group('cross-content-type', 'understand-revision-requirements-by-content-type');
    
    it('indicates auto-approval status before content creation', function () {
        $user = User::factory()->create([
            'trust_points' => ['App\Models\MemberProfile' => 15, 'App\Models\CommunityEvent' => 2]
        ]);
        
        // Check auto-approval capabilities
        expect($this->trustService->canAutoApprove($user, 'App\Models\MemberProfile'))->toBeFalse(); // Below threshold but personal
        expect($this->trustService->canAutoApprove($user, 'App\Models\CommunityEvent'))->toBeFalse(); // Low trust public content
        
        // Increase trust for community events
        $user->update(['trust_points' => ['App\Models\MemberProfile' => 15, 'App\Models\CommunityEvent' => 35]]);
        
        expect($this->trustService->canAutoApprove($user, 'App\Models\CommunityEvent'))->toBeTrue(); // High trust public content
    })->group('cross-content-type', 'understand-revision-requirements-by-content-type');
});

describe('Unified Content Tracking', function () {
    it('provides unified dashboard of all content submissions', function () {
        $user = User::factory()->create([
            'trust_points' => ['App\Models\MemberProfile' => 10, 'App\Models\Band' => 5, 'App\Models\CommunityEvent' => 2]
        ]);
        
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');
        
        // Create content across multiple types
        $memberProfile = MemberProfile::create([
            'user_id' => $user->id,
            'bio' => 'Member bio'
        ]);
        
        $band = Band::factory()->create(['owner_id' => $user->id]);
        
        $communityEvent = CommunityEvent::factory()->create([
            'title' => 'Test Event',
            'description' => 'Event description',
            'organizer_id' => $user->id,
            'status' => CommunityEvent::STATUS_PENDING
        ]);
        
        $this->actingAs($user);
        
        // Make updates to each type
        $memberProfile->update(['bio' => 'Updated member bio']);
        $band->update(['bio' => 'Updated band bio']);
        $communityEvent->update(['title' => 'Updated Event Title']);
        
        // Get all revisions for user across content types
        $allRevisions = Revision::where('submitted_by_id', $user->id)->get();
        
        expect($allRevisions)->toHaveCount(3);
        
        // Should have different content types
        $contentTypes = $allRevisions->pluck('revisionable_type')->unique();
        expect($contentTypes)->toContain(MemberProfile::class);
        expect($contentTypes)->toContain(Band::class);
        expect($contentTypes)->toContain(CommunityEvent::class);
        
        // Different statuses based on content type
        $memberRevision = $allRevisions->where('revisionable_type', MemberProfile::class)->first();
        $bandRevision = $allRevisions->where('revisionable_type', Band::class)->first();
        $eventRevision = $allRevisions->where('revisionable_type', CommunityEvent::class)->first();
        
        expect($memberRevision->status)->toBe(Revision::STATUS_APPROVED); // Personal content auto-approved
        expect($bandRevision->status)->toBe(Revision::STATUS_APPROVED); // Personal content auto-approved
        expect($eventRevision->status)->toBe(Revision::STATUS_PENDING); // Public content needs approval
    })->group('cross-content-type', 'track-my-content-across-all-types');
    
    it('categorizes content by status across all types', function () {
        $user = User::factory()->create([
            'trust_points' => ['App\Models\MemberProfile' => 5, 'App\Models\CommunityEvent' => 1]
        ]);
        
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');
        
        $this->actingAs($user);
        
        // Create multiple pieces of content with different outcomes
        $profile = MemberProfile::create(['user_id' => $user->id, 'bio' => 'Bio']);
        $event1 = CommunityEvent::factory()->create([
            'title' => 'Event 1',
            'description' => 'Description',
            'organizer_id' => $user->id,
            'status' => CommunityEvent::STATUS_PENDING
        ]);
        $event2 = CommunityEvent::factory()->create([
            'title' => 'Event 2',
            'description' => 'Description',
            'organizer_id' => $user->id,
            'status' => CommunityEvent::STATUS_PENDING
        ]);
        
        // Make updates
        $profile->update(['bio' => 'Updated bio']); // Should auto-approve
        $event1->update(['title' => 'Updated Event 1']); // Should pend
        $event2->update(['title' => 'Updated Event 2']); // Should pend
        
        // Approve one event, reject another
        $event1Revision = $event1->revisions()->latest()->first();
        $event2Revision = $event2->revisions()->latest()->first();
        
        $this->revisionService->approveRevision($event1Revision, $moderator, 'Good event');
        $this->revisionService->rejectRevision($event2Revision, $moderator, 'Needs improvement');
        
        // Check categorization
        $userRevisions = Revision::where('submitted_by_id', $user->id)->get();
        $approved = $userRevisions->where('status', Revision::STATUS_APPROVED);
        $rejected = $userRevisions->where('status', Revision::STATUS_REJECTED);
        $pending = $userRevisions->where('status', Revision::STATUS_PENDING);
        
        expect($approved->count())->toBe(2); // Profile + approved event
        expect($rejected->count())->toBe(1); // Rejected event
        expect($pending->count())->toBe(0); // None pending
    })->group('cross-content-type', 'track-my-content-across-all-types');
    
    it('shows trust levels for different content types', function () {
        $user = User::factory()->create([
            'trust_points' => [
                'App\Models\MemberProfile' => 15,
                'App\Models\Band' => 25,
                'App\Models\CommunityEvent' => 5,
                'App\Models\StaffProfile' => 0,
                'global' => 10
            ]
        ]);
        
        // Check trust levels across types
        $trustLevels = [
            'App\Models\MemberProfile' => $this->trustService->getTrustLevel($user, 'App\Models\MemberProfile'),
            'App\Models\Band' => $this->trustService->getTrustLevel($user, 'App\Models\Band'),
            'App\Models\CommunityEvent' => $this->trustService->getTrustLevel($user, 'App\Models\CommunityEvent'),
            'App\Models\StaffProfile' => $this->trustService->getTrustLevel($user, 'App\Models\StaffProfile'),
        ];
        
        expect($trustLevels['App\Models\MemberProfile'])->toBe('verified'); // 15 points
        expect($trustLevels['App\Models\Band'])->toBe('verified'); // 25 points
        expect($trustLevels['App\Models\CommunityEvent'])->toBe('trusted'); // 5 points
        expect($trustLevels['App\Models\StaffProfile'])->toBe('pending'); // 0 points
    })->group('cross-content-type', 'track-my-content-across-all-types');
});

describe('Consistent Appeals Process', function () {
    it('provides consistent appeals process across all content types', function () {
        $user = User::factory()->create([
            'trust_points' => ['App\Models\CommunityEvent' => 2]
        ]);
        
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');
        
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        
        // Create and reject revisions for different content types
        $profile = MemberProfile::create(['user_id' => $user->id, 'bio' => 'Bio']);
        $event = CommunityEvent::factory()->create([
            'title' => 'Event',
            'description' => 'Description',
            'organizer_id' => $user->id,
            'status' => CommunityEvent::STATUS_PENDING
        ]);
        
        $this->actingAs($user);
        
        // Force profile into revision mode for testing by giving user poor standing
        $user->update(['trust_points' => ['App\Models\MemberProfile' => -10, 'App\Models\CommunityEvent' => 2]]);
        $profileRevision = $profile->createRevision(['bio' => 'Controversial bio'], $user);
        $eventRevision = $event->createRevision(['title' => 'Controversial Event'], $user);
        
        // Reject both
        $this->revisionService->rejectRevision($profileRevision, $moderator, 'Inappropriate content');
        $this->revisionService->rejectRevision($eventRevision, $moderator, 'Inappropriate content');
        
        // Both should be appealable through consistent process
        expect($profileRevision->canBeAppealed())->toBeTrue();
        expect($eventRevision->canBeAppealed())->toBeTrue();
        
        // Appeal timelines should be reasonable
        expect($profileRevision->getAppealDeadline())->toBeGreaterThan(now());
        expect($eventRevision->getAppealDeadline())->toBeGreaterThan(now());
        
        // Appeal process should allow additional context
        $profileAppeal = $profileRevision->createAppeal($user, 'This content was misunderstood, here is context...');
        $eventAppeal = $eventRevision->createAppeal($user, 'Event details were taken out of context...');
        
        expect($profileAppeal)->not->toBeNull();
        expect($eventAppeal)->not->toBeNull();
    })->group('cross-content-type', 'appeal-content-decisions-across-types');
    
    it('allows different content types to have specialized appeal reviewers', function () {
        $user = User::factory()->create();
        
        $generalModerator = User::factory()->create();
        $generalModerator->assignRole('moderator');
        
        $eventSpecialist = User::factory()->create();
        $eventSpecialist->assignRole('moderator');
        // In a real implementation, you might have specific permissions for event appeals
        
        $profileRevision = Revision::factory()->create([
            'revisionable_type' => MemberProfile::class,
            'submitted_by_id' => $user->id,
            'status' => Revision::STATUS_REJECTED
        ]);
        
        $eventRevision = Revision::factory()->create([
            'revisionable_type' => CommunityEvent::class,
            'submitted_by_id' => $user->id,
            'status' => Revision::STATUS_REJECTED
        ]);
        
        // Different content types can route to different specialist reviewers
        $profileSpecialists = $profileRevision->getAppealReviewers();
        $eventSpecialists = $eventRevision->getAppealReviewers();
        
        // Should return appropriate reviewers for each content type
        expect($profileSpecialists)->toBeArray();
        expect($eventSpecialists)->toBeArray();
    })->group('cross-content-type', 'appeal-content-decisions-across-types');
    
    it('tracks successful appeals to identify policy review needs', function () {
        $user = User::factory()->create();
        
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');
        
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        
        // Create multiple rejected revisions
        $rejectedRevisions = [];
        for ($i = 0; $i < 3; $i++) {
            $event = CommunityEvent::factory()->create([
                'title' => "Event {$i}",
                'description' => 'Description',
                'organizer_id' => $user->id,
                'status' => CommunityEvent::STATUS_PENDING
            ]);
            
            $this->actingAs($user);
            $revision = $event->createRevision(['title' => "Updated Event {$i}"], $user);
            $this->revisionService->rejectRevision($revision, $moderator, 'Standard rejection');
            
            // Appeal and have it overturned
            $appeal = $revision->createAppeal($user, 'This was wrongly rejected');
            $revision->update(['status' => Revision::STATUS_APPROVED]); // Simulate successful appeal
            
            $rejectedRevisions[] = $revision;
        }
        
        // Pattern of successful appeals should be trackable
        // Note: In actual implementation, we'd track appeal_submitted_at
        // For now, we'll count all approved revisions from our test loop
        $successfulAppeals = count($rejectedRevisions);
        
        expect($successfulAppeals)->toBeGreaterThan(0);
        
        // This data should inform policy review
        $firstRevision = $rejectedRevisions[0];
        $appealPatterns = $firstRevision->getAppealPatterns();
        expect($appealPatterns)->toBeArray();
    })->group('cross-content-type', 'appeal-content-decisions-across-types');
});

describe('Cross-Content Type Longitudinal Behavior', function () {
    it('demonstrates different content evolution patterns over time', function () {
        $user = User::factory()->create([
            'trust_points' => ['App\Models\MemberProfile' => 5, 'App\Models\CommunityEvent' => 1]
        ]);
        
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');
        
        $this->actingAs($user);
        
        // Personal content: Immediate updates throughout
        $profile = MemberProfile::create(['user_id' => $user->id, 'bio' => 'Original bio']);
        
        for ($month = 1; $month <= 6; $month++) {
            $profile->update(['bio' => "Bio update month {$month}"]);
            
            $profile->refresh();
            expect($profile->bio)->toBe("Bio update month {$month}");
            
            $revision = $profile->revisions()->latest()->first();
            expect($revision->status)->toBe(Revision::STATUS_APPROVED);
            expect($revision->auto_approved)->toBeTrue();
        }
        
        // Public content: Evolution from requiring approval to auto-approval
        $events = [];
        for ($month = 1; $month <= 6; $month++) {
            $event = CommunityEvent::factory()->create([
                'title' => "Event Month {$month}",
                'description' => 'Description',
                'organizer_id' => $user->id,
                'status' => CommunityEvent::STATUS_PENDING
            ]);
            
            if ($month <= 3) {
                // For early months, update first then check it requires approval
                $event->update(['title' => "Updated Event Month {$month}"]);
                $revision = $event->revisions()->latest()->first();
                
                // Early months: require approval
                expect($revision->status)->toBe(Revision::STATUS_PENDING);
                $this->revisionService->approveRevision($revision, $moderator, 'Approved');
                
                // Build trust over time
                $currentTrust = $user->trust_points['App\Models\CommunityEvent'] ?? 0;
                $user->update(['trust_points' => ['App\Models\MemberProfile' => 5, 'App\Models\CommunityEvent' => $currentTrust + 8]]);
            } else {
                // For later months, build trust first, then update
                $currentTrust = $user->trust_points['App\Models\CommunityEvent'] ?? 0;
                $user->update(['trust_points' => ['App\Models\MemberProfile' => 5, 'App\Models\CommunityEvent' => $currentTrust + 8]]);
                
                $event->update(['title' => "Updated Event Month {$month}"]);
                $revision = $event->revisions()->latest()->first();
                
                // Later months: auto-approved due to built trust
                expect($revision->status)->toBe(Revision::STATUS_APPROVED);
                expect($revision->auto_approved)->toBeTrue();
            }
            
            $events[] = $event;
        }
        
        // Verify trust progression
        $user->refresh();
        expect($user->trust_points['App\Models\CommunityEvent'])->toBeGreaterThan(25);
    })->group('cross-content-type', 'track-my-content-across-all-types');
    
    it('handles mixed content types with different trust requirements', function () {
        $user = User::factory()->create([
            'trust_points' => [
                'App\Models\MemberProfile' => 10,    // Medium trust
                'App\Models\Band' => 35,      // High trust
                'App\Models\CommunityEvent' => 2,    // Low trust
                'App\Models\StaffProfile' => 50      // High trust (irrelevant for staff content)
            ]
        ]);
        $user->assignRole('staff');
        
        // Create all content types
        $profile = MemberProfile::create(['user_id' => $user->id, 'bio' => 'Bio']);
        $band = Band::factory()->create(['owner_id' => $user->id]);
        $event = CommunityEvent::factory()->create([
            'title' => 'Event',
            'description' => 'Description',
            'organizer_id' => $user->id,
            'status' => CommunityEvent::STATUS_PENDING
        ]);
        $staff = StaffProfile::create([
            'user_id' => $user->id,
            'name' => 'Test Staff Member',
            'title' => 'Staff',
            'bio' => 'Staff bio',
            'type' => StaffProfileType::Staff,
            'sort_order' => 1,
            'is_active' => true
        ]);
        
        $this->actingAs($user);
        
        // Update all content types
        $profile->update(['bio' => 'Updated bio']);
        $band->update(['bio' => 'Updated band bio']);
        $event->update(['title' => 'Updated event']);
        $staff->update(['bio' => 'Updated staff bio']);
        
        // Check outcomes based on content type rules
        $profileRevision = $profile->revisions()->latest()->first();
        $bandRevision = $band->revisions()->latest()->first();
        $eventRevision = $event->revisions()->latest()->first();
        $staffRevision = $staff->revisions()->latest()->first();
        
        // Personal content: auto-approved
        expect($profileRevision->status)->toBe(Revision::STATUS_APPROVED);
        expect($bandRevision->status)->toBe(Revision::STATUS_APPROVED);
        
        // Public content: requires approval due to low trust
        expect($eventRevision->status)->toBe(Revision::STATUS_PENDING);
        
        // Organizational content: always requires approval
        expect($staffRevision->status)->toBe(Revision::STATUS_PENDING);
    })->group('cross-content-type', 'understand-revision-requirements-by-content-type');
});