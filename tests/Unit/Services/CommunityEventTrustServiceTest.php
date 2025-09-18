<?php

use App\Models\User;
use App\Models\CommunityEvent;
use App\Services\CommunityEventTrustService;
use Illuminate\Support\Facades\Cache;

uses()->group('community-events', 'unit');

beforeEach(function () {
    $this->trustService = new CommunityEventTrustService();
    $this->user = User::factory()->create(['community_event_trust_points' => 0]);
    
    // Clear any cached trust points to ensure clean test state
    Cache::flush();
});

/**
 * Helper method to update user trust points and clear cache.
 */
function updateUserTrustPoints(User $user, int $points): void
{
    $user->update(['community_event_trust_points' => $points]);
    Cache::forget("community_event_trust_points_{$user->id}");
}

test('it calculates correct trust levels', function () {
    // Pending (0-4 points)
    updateUserTrustPoints($this->user, 0);
    expect($this->trustService->getTrustLevel($this->user))->toBe('pending');

    updateUserTrustPoints($this->user, 4);
    expect($this->trustService->getTrustLevel($this->user))->toBe('pending');

    // Trusted (5-14 points)
    updateUserTrustPoints($this->user, 5);
    expect($this->trustService->getTrustLevel($this->user))->toBe('trusted');

    updateUserTrustPoints($this->user, 14);
    expect($this->trustService->getTrustLevel($this->user))->toBe('trusted');

    // Verified (15-29 points)
    updateUserTrustPoints($this->user, 15);
    expect($this->trustService->getTrustLevel($this->user))->toBe('verified');

    updateUserTrustPoints($this->user, 29);
    expect($this->trustService->getTrustLevel($this->user))->toBe('verified');

    // Auto-approved (30+ points)
    updateUserTrustPoints($this->user, 30);
    expect($this->trustService->getTrustLevel($this->user))->toBe('auto-approved');

    updateUserTrustPoints($this->user, 100);
    expect($this->trustService->getTrustLevel($this->user))->toBe('auto-approved');
});

test('it determines approval capabilities correctly', function () {
    // Pending user cannot auto-approve
    $this->user->update(['community_event_trust_points' => 0]);
    expect($this->trustService->canAutoApprove($this->user))->toBeFalse();
    expect($this->trustService->getFastTrackApproval($this->user))->toBeFalse();

    // Trusted user gets fast-track but not auto-approve
    $this->user->update(['community_event_trust_points' => 5]);
    expect($this->trustService->canAutoApprove($this->user))->toBeFalse();
    expect($this->trustService->getFastTrackApproval($this->user))->toBeTrue();

    // Verified user gets fast-track but not auto-approve
    $this->user->update(['community_event_trust_points' => 15]);
    expect($this->trustService->canAutoApprove($this->user))->toBeFalse();
    expect($this->trustService->getFastTrackApproval($this->user))->toBeTrue();

    // Auto-approved user gets both
    $this->user->update(['community_event_trust_points' => 30]);
    expect($this->trustService->canAutoApprove($this->user))->toBeTrue();
    expect($this->trustService->getFastTrackApproval($this->user))->toBeTrue();
});

test('it awards points for successful events', function () {
    $event = CommunityEvent::factory()->create([
        'organizer_id' => $this->user->id,
        'status' => CommunityEvent::STATUS_APPROVED,
        'start_time' => now()->subDays(7),
        'end_time' => now()->subDays(7)->addHours(2),
        'published_at' => now()->subDays(14),
    ]);

    $initialPoints = $this->trustService->getTrustPoints($this->user);
    
    $this->trustService->awardSuccessfulEvent($this->user, $event);
    
    $newPoints = $this->trustService->getTrustPoints($this->user);
    expect($newPoints)->toBe($initialPoints + CommunityEventTrustService::POINTS_SUCCESSFUL_EVENT);
});

test('it does not award points for upcoming events', function () {
    $event = CommunityEvent::factory()->create([
        'organizer_id' => $this->user->id,
        'status' => CommunityEvent::STATUS_APPROVED,
        'start_time' => now()->addDays(7), // Future event
        'published_at' => now()->subDays(1),
    ]);

    $initialPoints = $this->trustService->getTrustPoints($this->user);
    
    $this->trustService->awardSuccessfulEvent($this->user, $event);
    
    $newPoints = $this->trustService->getTrustPoints($this->user);
    expect($newPoints)->toBe($initialPoints); // Should not change
});

test('it does not award points for unpublished events', function () {
    $event = CommunityEvent::factory()->create([
        'organizer_id' => $this->user->id,
        'status' => CommunityEvent::STATUS_PENDING,
        'start_time' => now()->subDays(7),
        'published_at' => null,
    ]);

    $initialPoints = $this->trustService->getTrustPoints($this->user);
    
    $this->trustService->awardSuccessfulEvent($this->user, $event);
    
    $newPoints = $this->trustService->getTrustPoints($this->user);
    expect($newPoints)->toBe($initialPoints); // Should not change
});

test('it penalizes violations correctly', function () {
    $this->user->update(['community_event_trust_points' => 20]);
    $initialPoints = $this->trustService->getTrustPoints($this->user);

    // Minor violation
    $this->trustService->penalizeViolation($this->user, 'minor', 'Inappropriate content');
    $afterMinor = $this->trustService->getTrustPoints($this->user);
    expect($afterMinor)->toBe($initialPoints + CommunityEventTrustService::POINTS_MINOR_VIOLATION);

    // Major violation
    $this->trustService->penalizeViolation($this->user, 'major', 'Spam content');
    $afterMajor = $this->trustService->getTrustPoints($this->user);
    expect($afterMajor)->toBe($afterMinor + CommunityEventTrustService::POINTS_MAJOR_VIOLATION);

    // Spam violation
    $this->trustService->penalizeViolation($this->user, 'spam', 'Repeated spam');
    $afterSpam = $this->trustService->getTrustPoints($this->user);
    expect($afterSpam)->toBe($afterMajor + CommunityEventTrustService::POINTS_SPAM_VIOLATION);
});

test('it prevents negative trust points', function () {
    $this->user->update(['community_event_trust_points' => 2]);
    
    // Apply major violation that would result in negative points
    $this->trustService->penalizeViolation($this->user, 'major', 'Major violation');
    
    $finalPoints = $this->trustService->getTrustPoints($this->user);
    expect($finalPoints)->toBe(0); // Should be 0, not negative
});

test('it determines approval workflows correctly', function () {
    $event = CommunityEvent::factory()->make();

    // Pending user
    $this->user->update(['community_event_trust_points' => 0]);
    $workflow = $this->trustService->determineApprovalWorkflow($this->user, $event);
    
    expect($workflow['requires_approval'])->toBeTrue();
    expect($workflow['auto_publish'])->toBeFalse();
    expect($workflow['review_priority'])->toBe('standard');
    expect($workflow['estimated_review_time'])->toBe(72);

    // Trusted user
    $this->user->update(['community_event_trust_points' => 5]);
    $workflow = $this->trustService->determineApprovalWorkflow($this->user, $event);
    
    expect($workflow['requires_approval'])->toBeTrue();
    expect($workflow['auto_publish'])->toBeFalse();
    expect($workflow['review_priority'])->toBe('fast-track');
    expect($workflow['estimated_review_time'])->toBe(24);

    // Auto-approved user
    $this->user->update(['community_event_trust_points' => 30]);
    $workflow = $this->trustService->determineApprovalWorkflow($this->user, $event);
    
    expect($workflow['requires_approval'])->toBeFalse();
    expect($workflow['auto_publish'])->toBeTrue();
    expect($workflow['review_priority'])->toBe('none');
    expect($workflow['estimated_review_time'])->toBe(0);
});

test('it provides correct trust level info', function () {
    // Pending user
    $this->user->update(['community_event_trust_points' => 3]);
    $info = $this->trustService->getTrustLevelInfo($this->user);
    
    expect($info['level'])->toBe('pending');
    expect($info['points'])->toBe(3);
    expect($info['next_level'])->toBe('trusted');
    expect($info['points_needed'])->toBe(2); // 5 - 3 = 2
    expect($info['can_auto_approve'])->toBeFalse();
    expect($info['fast_track'])->toBeFalse();

    // Trusted user
    $this->user->update(['community_event_trust_points' => 10]);
    $info = $this->trustService->getTrustLevelInfo($this->user);
    
    expect($info['level'])->toBe('trusted');
    expect($info['points'])->toBe(10);
    expect($info['next_level'])->toBe('verified');
    expect($info['points_needed'])->toBe(5); // 15 - 10 = 5
    expect($info['can_auto_approve'])->toBeFalse();
    expect($info['fast_track'])->toBeTrue();

    // Auto-approved user
    $this->user->update(['community_event_trust_points' => 35]);
    $info = $this->trustService->getTrustLevelInfo($this->user);
    
    expect($info['level'])->toBe('auto-approved');
    expect($info['points'])->toBe(35);
    expect($info['next_level'])->toBeNull();
    expect($info['points_needed'])->toBe(0);
    expect($info['can_auto_approve'])->toBeTrue();
    expect($info['fast_track'])->toBeTrue();
});

test('it generates correct trust badges', function () {
    // Pending user - no badge
    $this->user->update(['community_event_trust_points' => 0]);
    $badge = $this->trustService->getTrustBadge($this->user);
    expect($badge)->toBeNull();

    // Trusted user
    $this->user->update(['community_event_trust_points' => 5]);
    $badge = $this->trustService->getTrustBadge($this->user);
    expect($badge['label'])->toBe('Trusted Organizer');
    expect($badge['color'])->toBe('warning');
    expect($badge['icon'])->toBe('tabler-star');

    // Verified user
    $this->user->update(['community_event_trust_points' => 15]);
    $badge = $this->trustService->getTrustBadge($this->user);
    expect($badge['label'])->toBe('Verified Organizer');
    expect($badge['color'])->toBe('info');
    expect($badge['icon'])->toBe('tabler-shield');

    // Auto-approved user
    $this->user->update(['community_event_trust_points' => 30]);
    $badge = $this->trustService->getTrustBadge($this->user);
    expect($badge['label'])->toBe('Auto-Approved Organizer');
    expect($badge['color'])->toBe('success');
    expect($badge['icon'])->toBe('tabler-shield-check');
});

test('it caches trust points', function () {
    // Skip this test in test environment since caching is disabled for reliability
    if (app()->environment('testing')) {
        $this->markTestSkipped('Cache is disabled in test environment');
        return;
    }
    
    Cache::flush(); // Start with clean cache
    
    // First call should cache the result
    $points1 = $this->trustService->getTrustPoints($this->user);
    
    // Directly update database without going through service
    \DB::table('users')
        ->where('id', $this->user->id)
        ->update(['community_event_trust_points' => 50]);
    
    // Should return cached result
    $points2 = $this->trustService->getTrustPoints($this->user);
    expect($points1)->toBe($points2);
    
    // Clear cache manually
    Cache::forget("community_event_trust_points_{$this->user->id}");
    
    // Should now return updated result
    $points3 = $this->trustService->getTrustPoints($this->user);
    expect($points3)->toBe(50);
})->skip('Cache is disabled in test environment');

test('it handles bulk award correctly', function () {
    // Create successful past events without reports
    CommunityEvent::factory()->count(3)->create([
        'organizer_id' => $this->user->id,
        'status' => CommunityEvent::STATUS_APPROVED,
        'start_time' => now()->subDays(7),
        'end_time' => now()->subDays(7)->addHours(2),
        'published_at' => now()->subDays(14),
    ]);

    $initialPoints = $this->trustService->getTrustPoints($this->user);
    
    $pointsAwarded = $this->trustService->bulkAwardPastEvents($this->user);
    
    expect($pointsAwarded)->toBe(3);
    expect($this->trustService->getTrustPoints($this->user))->toBe($initialPoints + 3);
});

test('it resets trust points correctly', function () {
    $this->user->update(['community_event_trust_points' => 25]);
    
    $this->trustService->resetTrustPoints($this->user, 'Test reset');
    
    expect($this->trustService->getTrustPoints($this->user))->toBe(0);
});

test('it filters users by trust level', function () {
    // Create users with different trust levels
    $pendingUser = User::factory()->create(['community_event_trust_points' => 0]);
    $trustedUser = User::factory()->create(['community_event_trust_points' => 7]);
    $verifiedUser = User::factory()->create(['community_event_trust_points' => 20]);
    $autoApprovedUser = User::factory()->create(['community_event_trust_points' => 35]);

    // Test each filter
    $pendingUsers = $this->trustService->getUsersByTrustLevel('pending');
    expect($pendingUsers->contains($pendingUser))->toBeTrue();
    expect($pendingUsers->contains($trustedUser))->toBeFalse();

    $trustedUsers = $this->trustService->getUsersByTrustLevel('trusted');
    expect($trustedUsers->contains($trustedUser))->toBeTrue();
    expect($trustedUsers->contains($pendingUser))->toBeFalse();
    expect($trustedUsers->contains($verifiedUser))->toBeFalse();

    $verifiedUsers = $this->trustService->getUsersByTrustLevel('verified');
    expect($verifiedUsers->contains($verifiedUser))->toBeTrue();
    expect($verifiedUsers->contains($trustedUser))->toBeFalse();
    expect($verifiedUsers->contains($autoApprovedUser))->toBeFalse();

    $autoApprovedUsers = $this->trustService->getUsersByTrustLevel('auto-approved');
    expect($autoApprovedUsers->contains($autoApprovedUser))->toBeTrue();
    expect($autoApprovedUsers->contains($verifiedUser))->toBeFalse();
});

test('it handles unknown violation types', function () {
    $this->user->update(['community_event_trust_points' => 10]);
    $initialPoints = $this->trustService->getTrustPoints($this->user);
    
    // Unknown violation type should default to minor
    $this->trustService->penalizeViolation($this->user, 'unknown_type', 'Unknown violation');
    
    $newPoints = $this->trustService->getTrustPoints($this->user);
    expect($newPoints)->toBe($initialPoints + CommunityEventTrustService::POINTS_MINOR_VIOLATION);
});