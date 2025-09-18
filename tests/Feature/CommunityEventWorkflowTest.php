<?php

use App\Models\User;
use App\Models\CommunityEvent;
use App\Services\CommunityEventTrustService;
use Illuminate\Support\Facades\Cache;

uses()->group('community-events');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->trustService = app(CommunityEventTrustService::class);
    
    // Clear any cached trust points to ensure clean test state
    Cache::flush();
});

test('new member events require approval', function () {
    // New member has 0 trust points
    expect($this->trustService->getTrustPoints($this->user))->toBe(0);
    expect($this->trustService->getTrustLevel($this->user))->toBe('pending');
    
    // Create event
    $event = CommunityEvent::factory()->create([
        'organizer_id' => $this->user->id,
        'status' => CommunityEvent::STATUS_PENDING,
    ]);

    // Event should require approval
    $workflow = $this->trustService->determineApprovalWorkflow($this->user, $event);
    expect($workflow['requires_approval'])->toBeTrue();
    expect($workflow['auto_publish'])->toBeFalse();
    expect($workflow['review_priority'])->toBe('standard');
    expect($workflow['estimated_review_time'])->toBe(72);
});

test('trusted member gets fast track approval', function () {
    // Give user trusted status
    $this->user->update(['community_event_trust_points' => CommunityEventTrustService::TRUST_TRUSTED]);
    
    expect($this->trustService->getTrustLevel($this->user))->toBe('trusted');
    
    $event = CommunityEvent::factory()->create(['organizer_id' => $this->user->id]);
    
    $workflow = $this->trustService->determineApprovalWorkflow($this->user, $event);
    expect($workflow['requires_approval'])->toBeTrue();
    expect($workflow['auto_publish'])->toBeFalse();
    expect($workflow['review_priority'])->toBe('fast-track');
    expect($workflow['estimated_review_time'])->toBe(24);
});

test('auto approved member bypasses approval', function () {
    // Give user auto-approved status
    $this->user->update(['community_event_trust_points' => CommunityEventTrustService::TRUST_AUTO_APPROVED]);
    
    expect($this->trustService->getTrustLevel($this->user))->toBe('auto-approved');
    
    $event = CommunityEvent::factory()->create(['organizer_id' => $this->user->id]);
    
    $workflow = $this->trustService->determineApprovalWorkflow($this->user, $event);
    expect($workflow['requires_approval'])->toBeFalse();
    expect($workflow['auto_publish'])->toBeTrue();
    expect($workflow['review_priority'])->toBe('none');
    expect($workflow['estimated_review_time'])->toBe(0);
});

test('successful events award trust points', function () {
    // Create a past approved event
    $event = CommunityEvent::factory()->create([
        'organizer_id' => $this->user->id,
        'status' => CommunityEvent::STATUS_APPROVED,
        'start_time' => now()->subDays(7),
        'end_time' => now()->subDays(7)->addHours(2),
        'published_at' => now()->subDays(14),
    ]);

    $initialPoints = $this->trustService->getTrustPoints($this->user);
    
    // Award points for successful event
    $this->trustService->awardSuccessfulEvent($this->user, $event);
    
    $newPoints = $this->trustService->getTrustPoints($this->user);
    expect($newPoints)->toBe($initialPoints + CommunityEventTrustService::POINTS_SUCCESSFUL_EVENT);
});

test('violations deduct trust points', function () {
    // Start with some points
    $this->user->update(['community_event_trust_points' => 10]);
    
    $initialPoints = $this->trustService->getTrustPoints($this->user);
    
    // Penalize for violation
    $this->trustService->penalizeViolation($this->user, 'minor', 'Inappropriate content');
    
    $newPoints = $this->trustService->getTrustPoints($this->user);
    expect($newPoints)->toBe($initialPoints + CommunityEventTrustService::POINTS_MINOR_VIOLATION);
    expect($newPoints)->toBeGreaterThanOrEqual(0); // Should not go below 0
});

test('trust points cannot go negative', function () {
    // Start with 1 point
    $this->user->update(['community_event_trust_points' => 1]);
    
    // Apply major violation (should be -5 points)
    $this->trustService->penalizeViolation($this->user, 'major', 'Spam content');
    
    $finalPoints = $this->trustService->getTrustPoints($this->user);
    expect($finalPoints)->toBe(0); // Should be 0, not negative
});

test('event approval updates status and published date', function () {
    $event = CommunityEvent::factory()->pending()->create([
        'organizer_id' => $this->user->id,
    ]);

    expect($event->status)->toBe(CommunityEvent::STATUS_PENDING);
    expect($event->published_at)->toBeNull();
    expect($event->isPublished())->toBeFalse();

    // Approve the event
    $event->update([
        'status' => CommunityEvent::STATUS_APPROVED,
        'published_at' => now(),
    ]);

    $event->refresh();
    expect($event->status)->toBe(CommunityEvent::STATUS_APPROVED);
    expect($event->published_at)->not->toBeNull();
    expect($event->isPublished())->toBeTrue();
});

test('public events are visible to all', function () {
    $publicEvent = CommunityEvent::factory()->approved()->create([
        'visibility' => CommunityEvent::VISIBILITY_PUBLIC,
    ]);

    expect($publicEvent->isPublic())->toBeTrue();
    
    // Should appear in public queries
    $publicEvents = CommunityEvent::publishedPublic()->get();
    expect($publicEvents->contains($publicEvent))->toBeTrue();
});

test('members only events are restricted', function () {
    $membersOnlyEvent = CommunityEvent::factory()->approved()->create([
        'visibility' => CommunityEvent::VISIBILITY_MEMBERS_ONLY,
    ]);

    expect($membersOnlyEvent->isPublic())->toBeFalse();
    
    // Should not appear in public queries
    $publicEvents = CommunityEvent::publishedPublic()->get();
    expect($publicEvents->contains($membersOnlyEvent))->toBeFalse();
});

test('local events filter works', function () {
    $localEvent = CommunityEvent::factory()->create([
        'distance_from_corvallis' => 25, // 25 minutes
    ]);

    $distantEvent = CommunityEvent::factory()->create([
        'distance_from_corvallis' => 90, // 90 minutes
    ]);

    $localEvents = CommunityEvent::local(30)->get(); // Within 30 minutes
    
    expect($localEvents->contains($localEvent))->toBeTrue();
    expect($localEvents->contains($distantEvent))->toBeFalse();
});

test('upcoming events filter works', function () {
    $upcomingEvent = CommunityEvent::factory()->upcoming()->approved()->create();
    
    $pastEvent = CommunityEvent::factory()->approved()->create([
        'start_time' => now()->subDays(7),
        'end_time' => now()->subDays(7)->addHours(2),
    ]);

    $upcomingEvents = CommunityEvent::approvedUpcoming()->get();
    
    expect($upcomingEvents->contains($upcomingEvent))->toBeTrue();
    expect($upcomingEvents->contains($pastEvent))->toBeFalse();
});

test('event organizer relationship works', function () {
    $event = CommunityEvent::factory()->create([
        'organizer_id' => $this->user->id,
    ]);

    expect($event->isOrganizedBy($this->user))->toBeTrue();
    expect($event->organizer->id)->toBe($this->user->id);
    expect($event->organizer->name)->toBe($this->user->name);
});

test('event trust badge reflects organizer status', function () {
    // Create event with new organizer
    $event = CommunityEvent::factory()->create([
        'organizer_id' => $this->user->id,
    ]);

    expect($event->getOrganizerTrustLevel())->toBe('pending');
    expect($event->getOrganizerTrustBadge())->toBeNull();

    // Upgrade organizer to verified
    $this->user->update(['community_event_trust_points' => CommunityEventTrustService::TRUST_VERIFIED]);
    
    expect($event->getOrganizerTrustLevel())->toBe('verified');
    $badge = $event->getOrganizerTrustBadge();
    expect($badge)->not->toBeNull();
    expect($badge['label'])->toBe('Verified Organizer');
    expect($badge['color'])->toBe('info');
});

test('event date range formatting works', function () {
    // Same day event
    $sameDayEvent = CommunityEvent::factory()->create([
        'start_time' => now()->setTime(19, 0), // 7:00 PM
        'end_time' => now()->setTime(22, 0),   // 10:00 PM
    ]);

    $dateRange = $sameDayEvent->date_range;
    expect($dateRange)->toContain('7:00 PM - 10:00 PM');

    // Multi-day event
    $multiDayEvent = CommunityEvent::factory()->create([
        'start_time' => now()->setTime(19, 0),
        'end_time' => now()->addDay()->setTime(22, 0),
    ]);

    $multiDayRange = $multiDayEvent->date_range;
    expect($multiDayRange)->toContain('7:00 PM');
    expect($multiDayRange)->toContain('10:00 PM');
});

test('event duration calculation works', function () {
    $event = CommunityEvent::factory()->create([
        'start_time' => now()->setTime(19, 0), // 7:00 PM
        'end_time' => now()->setTime(22, 30),   // 10:30 PM
    ]);

    expect($event->duration)->toBe(3.5); // 3.5 hours
});

test('event ticketing logic works', function () {
    // Free event
    $freeEvent = CommunityEvent::factory()->create([
        'ticket_url' => null,
        'ticket_price' => null,
    ]);

    expect($freeEvent->hasTickets())->toBeFalse();
    expect($freeEvent->isFree())->toBeTrue();
    expect($freeEvent->ticket_price_display)->toBe('Free');

    // Paid event
    $paidEvent = CommunityEvent::factory()->withTickets()->create([
        'ticket_price' => 25.50,
    ]);

    expect($paidEvent->hasTickets())->toBeTrue();
    expect($paidEvent->isFree())->toBeFalse();
    expect($paidEvent->ticket_price_display)->toBe('$25.50');

    // Ticketed but free
    $ticketedFreeEvent = CommunityEvent::factory()->create([
        'ticket_url' => 'https://example.com/tickets',
        'ticket_price' => 0,
    ]);

    expect($ticketedFreeEvent->hasTickets())->toBeTrue();
    expect($ticketedFreeEvent->isFree())->toBeTrue();
    expect($ticketedFreeEvent->ticket_price_display)->toBe('Free');
});

test('bulk trust point award works', function () {
    // Create multiple successful past events
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

test('trust level progression works', function () {
    $trustInfo = $this->trustService->getTrustLevelInfo($this->user);
    
    // Start at pending
    expect($trustInfo['level'])->toBe('pending');
    expect($trustInfo['next_level'])->toBe('trusted');
    expect($trustInfo['points_needed'])->toBe(CommunityEventTrustService::TRUST_TRUSTED);

    // Progress to trusted
    $this->user->update(['community_event_trust_points' => CommunityEventTrustService::TRUST_TRUSTED]);
    $trustInfo = $this->trustService->getTrustLevelInfo($this->user);
    
    expect($trustInfo['level'])->toBe('trusted');
    expect($trustInfo['next_level'])->toBe('verified');
    expect($trustInfo['points_needed'])->toBe(CommunityEventTrustService::TRUST_VERIFIED - CommunityEventTrustService::TRUST_TRUSTED);

    // Progress to auto-approved
    $this->user->update(['community_event_trust_points' => CommunityEventTrustService::TRUST_AUTO_APPROVED]);
    $trustInfo = $this->trustService->getTrustLevelInfo($this->user);
    
    expect($trustInfo['level'])->toBe('auto-approved');
    expect($trustInfo['next_level'])->toBeNull();
    expect($trustInfo['points_needed'])->toBe(0);
});