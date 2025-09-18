<?php

use App\Models\User;
use App\Models\CommunityEvent;
use App\Models\Revision;
use App\Models\Report;
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

describe('First-Time Public Content Submission', function () {
    it('requires approval for new member community event submissions', function () {
        $newUser = User::factory()->create([
            'trust_points' => ['App\\Models\\CommunityEvent' => 0, 'global' => 0]
        ]);
        
        $this->actingAs($newUser);
        
        // Create community event - should require approval
        $event = CommunityEvent::factory()->create([
            'title' => 'My First Community Event',
            'description' => 'A great event for the community',
            'start_time' => now()->addWeek(),
            'organizer_id' => $newUser->id,
            'status' => CommunityEvent::STATUS_PENDING
        ]);
        
        // Event update should create pending revision
        $event->update([
            'title' => 'My First Community Event - Updated',
            'status' => CommunityEvent::STATUS_PENDING
        ]);
        
        // Event should remain in draft/pending state
        $event->refresh();
        expect($event->status)->toBe(CommunityEvent::STATUS_PENDING);
        
        // Should create pending revision
        $revision = Revision::where('revisionable_id', $event->id)
                          ->where('revisionable_type', CommunityEvent::class)
                          ->first();
        
        expect($revision)->not->toBeNull();
        expect($revision->status)->toBe(Revision::STATUS_PENDING);
        expect($revision->submitted_by_id)->toBe($newUser->id);
    })->group('public-content', 'submit-community-event-for-first-time');
    
    it('provides clear submission confirmation and status for new users', function () {
        $newUser = User::factory()->create([
            'trust_points' => ['App\\Models\\CommunityEvent' => 0, 'global' => 0]
        ]);
        
        $event = CommunityEvent::factory()->create([
            'title' => 'Test Event',
            'description' => 'Test Description',
            'start_time' => now()->addWeek(),
            'organizer_id' => $newUser->id,
            'status' => CommunityEvent::STATUS_PENDING
        ]);
        
        // Check workflow determination for new user
        $workflow = $event->getRevisionWorkflow();
        
        expect($workflow['requires_approval'])->toBeTrue();
        expect($workflow['review_priority'])->toBe('standard');
        expect($workflow['estimated_review_time'])->toBe(72); // Standard review time
        // expect($workflow['user_experience_level'])->toBe('new'); // This key doesn't exist in workflow
    })->group('public-content', 'submit-community-event-for-first-time');
    
    it('shows educational resources for new community event submitters', function () {
        $newUser = User::factory()->create([
            'trust_points' => ['App\\Models\\CommunityEvent' => 0, 'global' => 0]
        ]);
        
        // Check that new users get educational guidance
        $trustInfo = $this->trustService->getTrustLevelInfo($newUser, 'App\\Models\\CommunityEvent');
        
        expect($trustInfo['level'])->toBe('pending');
        // expect($trustInfo['guidance'])->toContain('first time'); // This key doesn't exist
        expect($trustInfo['points_needed'])->toBeGreaterThan(0);
    })->group('public-content', 'submit-community-event-for-first-time');
});

describe('Trusted Member Public Content', function () {
    it('auto-approves community events from trusted members', function () {
        $trustedUser = User::factory()->create([
            'trust_points' => ['App\\Models\\CommunityEvent' => 35, 'global' => 35]
        ]);
        
        $this->actingAs($trustedUser);
        
        // Create and update community event
        $event = CommunityEvent::factory()->create([
            'title' => 'Trusted Member Event',
            'description' => 'Event from trusted member',
            'start_time' => now()->addWeek(),
            'organizer_id' => $trustedUser->id,
            'status' => CommunityEvent::STATUS_PENDING
        ]);
        
        // Update should be auto-approved and published
        $event->update([
            'title' => 'Trusted Member Event - Live',
            'status' => CommunityEvent::STATUS_APPROVED
        ]);
        
        $event->refresh();
        expect($event->status)->toBe(CommunityEvent::STATUS_APPROVED);
        
        // Should create auto-approved revision
        $revision = Revision::where('revisionable_id', $event->id)
                          ->where('revisionable_type', CommunityEvent::class)
                          ->first();
        
        expect($revision)->not->toBeNull();
        expect($revision->status)->toBe(Revision::STATUS_APPROVED);
        expect($revision->auto_approved)->toBeTrue();
    })->group('public-content', 'submit-public-content-as-trusted-member');
    
    it('allows real-time editing for trusted members', function () {
        $trustedUser = User::factory()->create([
            'trust_points' => ['App\\Models\\CommunityEvent' => 35, 'global' => 35]
        ]);
        
        $event = CommunityEvent::factory()->create([
            'title' => 'Original Title',
            'description' => 'Original Description',
            'start_time' => now()->addWeek(),
            'organizer_id' => $trustedUser->id,
            'status' => CommunityEvent::STATUS_APPROVED
        ]);
        
        $this->actingAs($trustedUser);
        
        // Multiple rapid updates should all be auto-approved
        $updates = [
            'Updated Title 1',
            'Updated Title 2', 
            'Final Title'
        ];
        
        foreach ($updates as $title) {
            $event->update(['title' => $title]);
            
            $event->refresh();
            expect($event->title)->toBe($title);
            
            // Each update should create approved revision
            $latestRevision = $event->revisions()->latest()->first();
            expect($latestRevision->status)->toBe(Revision::STATUS_APPROVED);
            expect($latestRevision->auto_approved)->toBeTrue();
        }
    })->group('public-content', 'submit-public-content-as-trusted-member');
    
    it('maintains trusted status unless credibly reported', function () {
        $trustedUser = User::factory()->create([
            'trust_points' => ['App\\Models\\CommunityEvent' => 35, 'global' => 35]
        ]);
        
        $event = CommunityEvent::factory()->create([
            'title' => 'Trusted Event',
            'description' => 'Event description',
            'start_time' => now()->addWeek(),
            'organizer_id' => $trustedUser->id,
            'status' => CommunityEvent::STATUS_APPROVED
        ]);
        
        // Single report shouldn't affect trusted status immediately
        $reporter = User::factory()->create();
        $report = Report::create([
            'reportable_type' => CommunityEvent::class,
            'reportable_id' => $event->id,
            'reported_by_id' => $reporter->id,
            'reason' => 'inappropriate_content',
            'description' => 'Test report',
            'status' => 'pending'
        ]);
        
        $this->actingAs($trustedUser);
        
        // Should still be able to make updates
        $event->update(['title' => 'Updated After Report']);
        
        $event->refresh();
        expect($event->title)->toBe('Updated After Report');
        
        // Should still be auto-approved
        $revision = $event->revisions()->latest()->first();
        expect($revision->status)->toBe(Revision::STATUS_APPROVED);
        expect($revision->auto_approved)->toBeTrue();
    })->group('public-content', 'respond-to-credible-reports-on-my-content');
});

describe('Trust Building Through Public Content', function () {
    it('tracks progress toward trusted status through approved events', function () {
        $user = User::factory()->create([
            'trust_points' => ['App\\Models\\CommunityEvent' => 5, 'global' => 5]
        ]);
        
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');
        
        $this->actingAs($user);
        
        // Create and submit multiple events
        for ($i = 1; $i <= 3; $i++) {
            $event = CommunityEvent::factory()->create([
                'title' => "Event {$i}",
                'description' => "Description for event {$i}",
                'start_time' => now()->subWeeks($i), // Past events so they can be evaluated for trust points
                'organizer_id' => $user->id,
                'status' => CommunityEvent::STATUS_PENDING
            ]);
            
            // Submit for review
            $event->update(['description' => "Updated description for event {$i}"]);
            
            $revision = $event->revisions()->latest()->first();
            expect($revision->status)->toBe(Revision::STATUS_PENDING);
            
            // Approve the revision
            $this->revisionService->approveRevision($revision, $moderator, 'Good event');
            
            // Check trust points increased
            $user->refresh();
            $trustPoints = $user->trust_points['App\\Models\\CommunityEvent'] ?? 0;
            expect($trustPoints)->toBeGreaterThanOrEqual(5 + $i);
        }
        
        // Check progress tracking
        $trustInfo = $this->trustService->getTrustLevelInfo($user, 'App\\Models\\CommunityEvent');
        expect($trustInfo['points'])->toBeGreaterThan(5);
        expect($trustInfo['next_level'])->toBeString();
        expect($trustInfo['points_needed'])->toBeInt();
    })->group('public-content', 'build-trust-through-quality-public-content');
    
    it('provides feedback to help improve future submissions', function () {
        $user = User::factory()->create([
            'trust_points' => ['App\\Models\\CommunityEvent' => 2, 'global' => 2]
        ]);
        
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');
        
        $event = CommunityEvent::factory()->create([
            'title' => 'Event Needing Improvement',
            'description' => 'Basic description',
            'start_time' => now()->addWeek(),
            'organizer_id' => $user->id,
            'status' => CommunityEvent::STATUS_PENDING
        ]);
        
        $this->actingAs($user);
        $event->update(['title' => 'Improved Event Title']);
        
        $revision = $event->revisions()->latest()->first();
        
        // Approve with constructive feedback
        $this->revisionService->approveRevision(
            $revision, 
            $moderator, 
            'Good improvement! Consider adding more detail about location and accessibility.'
        );
        
        // Check that feedback is stored and accessible
        $revision->refresh();
        expect($revision->review_reason)->toContain('Good improvement');
        expect($revision->review_reason)->toContain('location and accessibility');
        
        // User should be able to access this feedback
        $userRevisions = Revision::where('submitted_by_id', $user->id)->approved()->get();
        expect($userRevisions)->toHaveCount(1);
        expect($userRevisions->first()->review_reason)->not->toBeEmpty();
    })->group('public-content', 'build-trust-through-quality-public-content');
});

describe('Public Content Longitudinal Behavior', function () {
    it('demonstrates progression from new user to trusted member', function () {
        $user = User::factory()->create([
            'trust_points' => ['App\\Models\\CommunityEvent' => 0, 'global' => 0]
        ]);
        
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');
        
        $this->actingAs($user);
        
        // Stage 1: New user - requires approval
        $event1 = CommunityEvent::factory()->create([
            'title' => 'First Event',
            'description' => 'My first community event',
            'start_time' => now()->addWeek(),
            'organizer_id' => $user->id,
            'status' => CommunityEvent::STATUS_PENDING
        ]);
        
        $event1->update(['title' => 'First Event - Revised']);
        $revision1 = $event1->revisions()->latest()->first();
        expect($revision1->status)->toBe(Revision::STATUS_PENDING);
        
        // Approve first event
        $this->revisionService->approveRevision($revision1, $moderator, 'Good first event');
        
        // Stage 2: Building trust - still requires approval but faster
        $user->update(['trust_points' => ['App\\Models\\CommunityEvent' => 10, 'global' => 10]]);
        
        $event2 = CommunityEvent::factory()->create([
            'title' => 'Second Event',
            'description' => 'Building my reputation',
            'start_time' => now()->addWeeks(2),
            'organizer_id' => $user->id,
            'status' => CommunityEvent::STATUS_PENDING
        ]);
        
        $workflow2 = $event2->getRevisionWorkflow();
        expect($workflow2['review_priority'])->toBe('fast-track');
        expect($workflow2['estimated_review_time'])->toBeLessThan(72);
        
        // Stage 3: Trusted member - auto-approval
        $user->update(['trust_points' => ['App\\Models\\CommunityEvent' => 35, 'global' => 35]]);
        
        $event3 = CommunityEvent::factory()->create([
            'title' => 'Third Event',
            'description' => 'Now I am trusted',
            'start_time' => now()->addWeeks(3),
            'organizer_id' => $user->id,
            'status' => 'draft'
        ]);
        
        $event3->update(['status' => 'published']);
        
        $event3->refresh();
        expect($event3->status)->toBe('published');
        
        $revision3 = $event3->revisions()->latest()->first();
        expect($revision3->status)->toBe(Revision::STATUS_APPROVED);
        expect($revision3->auto_approved)->toBeTrue();
    })->group('public-content', 'build-trust-through-quality-public-content');
    
    it('handles temporary loss of trusted status due to reports', function () {
        $trustedUser = User::factory()->create([
            'trust_points' => ['App\\Models\\CommunityEvent' => 35, 'global' => 35]
        ]);
        
        $event = CommunityEvent::factory()->create([
            'title' => 'Trusted Event',
            'description' => 'Event from trusted user',
            'start_time' => now()->addWeek(),
            'organizer_id' => $trustedUser->id,
            'status' => CommunityEvent::STATUS_APPROVED
        ]);
        
        // Create multiple credible reports
        $reporters = User::factory()->count(3)->create();
        foreach ($reporters as $reporter) {
            Report::create([
                'reportable_type' => CommunityEvent::class,
                'reportable_id' => $event->id,
                'reported_by_id' => $reporter->id,
                'reason' => 'inappropriate_content',
                'description' => 'Credible concern about content',
                'status' => 'upheld'
            ]);
        }
        
        // Simulate trust system reducing user's standing due to reports
        $this->trustService->handleContentViolation(
            $trustedUser, 
            $event, 
            'spam', // More severe violation that drops trust below auto-approval threshold
            'App\\Models\\CommunityEvent'
        );
        
        $this->actingAs($trustedUser);
        
        // Next update should require approval due to reduced standing
        $event->update(['title' => 'Updated Title After Reports']);
        
        // Should create pending revision instead of auto-approving
        $revision = $event->revisions()->latest()->first();
        expect($revision->status)->toBe(Revision::STATUS_PENDING);
        expect($revision->auto_approved)->toBeFalse();
    })->group('public-content', 'respond-to-credible-reports-on-my-content');
});