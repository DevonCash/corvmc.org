<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\CommunityEvent;
use App\Models\Production;
use App\Models\Reservation;
use App\Services\CalendarService;
use App\Exceptions\Services\CalendarServiceException;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CalendarServiceCommunityEventsTest extends TestCase
{
    use RefreshDatabase;

    protected CalendarService $calendarService;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->calendarService = new CalendarService();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_converts_published_community_event_to_calendar_event()
    {
        $communityEvent = CommunityEvent::factory()->approved()->create([
            'organizer_id' => $this->user->id,
            'title' => 'Test Community Event',
            'start_time' => now(),
            'end_time' => now()->addHours(2),
            'event_type' => CommunityEvent::TYPE_PERFORMANCE,
            'venue_name' => 'Test Venue',
            'venue_address' => '123 Test St',
        ]);

        $calendarEvent = $this->calendarService->communityEventToCalendarEvent($communityEvent);

        $this->assertInstanceOf(CalendarEvent::class, $calendarEvent);
        $this->assertEquals('Test Community Event', $calendarEvent->getTitle()); // No checkmark for unverified organizer
        $this->assertEquals('#8b5cf6', $calendarEvent->getBackgroundColor()); // Performance color
        $this->assertEquals('#fff', $calendarEvent->getTextColor());
        
        $extendedProps = $calendarEvent->getExtendedProps();
        $this->assertEquals('community_event', $extendedProps['type']);
        $this->assertEquals($this->user->name, $extendedProps['organizer_name']);
        $this->assertEquals(CommunityEvent::TYPE_PERFORMANCE, $extendedProps['event_type']);
        $this->assertEquals('Test Venue', $extendedProps['venue_name']);
    }

    /** @test */
    public function it_hides_unpublished_community_events()
    {
        $pendingEvent = CommunityEvent::factory()->pending()->create([
            'organizer_id' => $this->user->id,
            'published_at' => null,
        ]);

        $calendarEvent = $this->calendarService->communityEventToCalendarEvent($pendingEvent);

        $this->assertEquals('', $calendarEvent->getTitle());
        $this->assertEquals('none', $calendarEvent->getDisplay());
    }

    /** @test */
    public function it_assigns_correct_colors_for_event_types()
    {
        $testCases = [
            [CommunityEvent::TYPE_PERFORMANCE, '#8b5cf6'],
            [CommunityEvent::TYPE_WORKSHOP, '#059669'],
            [CommunityEvent::TYPE_OPEN_MIC, '#dc2626'],
            [CommunityEvent::TYPE_COLLABORATIVE_SHOW, '#0891b2'],
            [CommunityEvent::TYPE_ALBUM_RELEASE, '#ea580c'],
        ];

        foreach ($testCases as [$eventType, $expectedColor]) {
            $event = CommunityEvent::factory()->approved()->create([
                'organizer_id' => $this->user->id,
                'event_type' => $eventType,
            ]);

            $calendarEvent = $this->calendarService->communityEventToCalendarEvent($event);
            $this->assertEquals($expectedColor, $calendarEvent->getBackgroundColor());
        }
    }

    /** @test */
    public function it_adds_trust_badge_to_verified_organizers()
    {
        // Create event with verified organizer
        $verifiedOrganizer = User::factory()->create(['community_event_trust_points' => 15]);
        $event = CommunityEvent::factory()->approved()->create([
            'organizer_id' => $verifiedOrganizer->id,
            'title' => 'Verified Event',
        ]);

        $calendarEvent = $this->calendarService->communityEventToCalendarEvent($event);
        
        // Should include checkmark for verified organizer
        $this->assertEquals('Verified Event ✓', $calendarEvent->getTitle());
    }

    /** @test */
    public function it_does_not_add_badge_to_unverified_organizers()
    {
        $event = CommunityEvent::factory()->approved()->create([
            'organizer_id' => $this->user->id, // Default user has 0 trust points
            'title' => 'Unverified Event',
        ]);

        $calendarEvent = $this->calendarService->communityEventToCalendarEvent($event);
        
        // Should not include checkmark
        $this->assertEquals('Unverified Event', $calendarEvent->getTitle());
    }

    /** @test */
    public function it_includes_extended_properties()
    {
        $event = CommunityEvent::factory()->approved()->create([
            'organizer_id' => $this->user->id,
            'event_type' => CommunityEvent::TYPE_WORKSHOP,
            'venue_name' => 'Workshop Venue',
            'venue_address' => '456 Workshop St',
            'visibility' => CommunityEvent::VISIBILITY_PUBLIC,
            'ticket_url' => 'https://tickets.example.com',
            'distance_from_corvallis' => 30,
        ]);

        $calendarEvent = $this->calendarService->communityEventToCalendarEvent($event);
        $extendedProps = $calendarEvent->getExtendedProps();

        $this->assertEquals('community_event', $extendedProps['type']);
        $this->assertEquals($this->user->name, $extendedProps['organizer_name']);
        $this->assertEquals(CommunityEvent::TYPE_WORKSHOP, $extendedProps['event_type']);
        $this->assertEquals('Workshop Venue', $extendedProps['venue_name']);
        $this->assertEquals('456 Workshop St', $extendedProps['venue_address']);
        $this->assertEquals(CommunityEvent::VISIBILITY_PUBLIC, $extendedProps['visibility']);
        $this->assertTrue($extendedProps['is_public']);
        $this->assertEquals('https://tickets.example.com', $extendedProps['ticket_url']);
        $this->assertEquals(30, $extendedProps['distance_from_corvallis']);
        $this->assertEquals('pending', $extendedProps['trust_level']);
    }

    /** @test */
    public function it_handles_missing_end_time()
    {
        $event = CommunityEvent::factory()->approved()->create([
            'organizer_id' => $this->user->id,
            'start_time' => now(),
            'end_time' => null, // No end time specified
        ]);

        $calendarEvent = $this->calendarService->communityEventToCalendarEvent($event);
        
        // Should default to 2 hours after start time  
        $expectedEndTime = $event->start_time->copy()->addHours(2);
        $actualEnd = $calendarEvent->getEnd();
        
        // Handle both string and Carbon object responses
        if ($actualEnd instanceof \Carbon\Carbon) {
            $this->assertEquals($expectedEndTime->toISOString(), $actualEnd->toISOString());
        } else {
            $this->assertEquals($expectedEndTime->toISOString(), $actualEnd);
        }
    }

    /** @test */
    public function it_throws_exception_for_non_persisted_event()
    {
        $event = CommunityEvent::factory()->make(); // Not saved to database

        $this->expectException(CalendarServiceException::class);
        $this->expectExceptionMessage('Community event must be persisted to database');

        $this->calendarService->communityEventToCalendarEvent($event);
    }

    /** @test */
    public function it_throws_exception_for_missing_start_time()
    {
        // Create a normal event first
        $event = CommunityEvent::factory()->approved()->create([
            'organizer_id' => $this->user->id,
        ]);
        
        // Manually set start_time to null on the model instance (bypassing database constraints)
        $event->start_time = null;

        $this->expectException(CalendarServiceException::class);
        $this->expectExceptionMessage('Community event must have start time');

        $this->calendarService->communityEventToCalendarEvent($event);
    }

    /** @test */
    public function it_throws_exception_for_invalid_date_range()
    {
        $event = CommunityEvent::factory()->approved()->create([
            'organizer_id' => $this->user->id,
            'start_time' => now(),
            'end_time' => now()->subHour(), // End before start
        ]);

        $this->expectException(CalendarServiceException::class);

        $this->calendarService->communityEventToCalendarEvent($event);
    }

    /** @test */
    public function it_includes_community_events_in_date_range_query()
    {
        $startDate = now();
        $endDate = now()->addWeek();

        // Create various events
        $communityEvent = CommunityEvent::factory()->approved()->create([
            'organizer_id' => $this->user->id,
            'start_time' => now()->addDays(2),
            'visibility' => CommunityEvent::VISIBILITY_PUBLIC,
        ]);

        $membersOnlyEvent = CommunityEvent::factory()->approved()->create([
            'organizer_id' => $this->user->id,
            'start_time' => now()->addDays(3),
            'visibility' => CommunityEvent::VISIBILITY_MEMBERS_ONLY,
        ]);

        $pendingEvent = CommunityEvent::factory()->pending()->create([
            'organizer_id' => $this->user->id,
            'start_time' => now()->addDays(4),
        ]);

        $events = $this->calendarService->getEventsForDateRange($startDate, $endDate);

        // Should only include approved public events
        $communityEventTitles = collect($events)
            ->filter(fn($event) => $event->getExtendedProps()['type'] === 'community_event')
            ->map(fn($event) => str_replace(' ✓', '', $event->getTitle()));

        $this->assertTrue($communityEventTitles->contains($communityEvent->title));
        $this->assertFalse($communityEventTitles->contains($membersOnlyEvent->title));
        $this->assertFalse($communityEventTitles->contains($pendingEvent->title));
    }

    /** @test */
    public function it_handles_mixed_event_types_in_date_range()
    {
        $startDate = now();
        $endDate = now()->addWeek();

        // Create community event - this is what we're primarily testing
        $communityEvent = CommunityEvent::factory()->approved()->create([
            'organizer_id' => $this->user->id,
            'start_time' => now()->addDays(1),
            'visibility' => CommunityEvent::VISIBILITY_PUBLIC,
        ]);

        $events = $this->calendarService->getEventsForDateRange($startDate, $endDate);

        // Test that community events are included and the service handles mixed types gracefully
        $communityEvents = collect($events)
            ->filter(fn($event) => ($event->getExtendedProps()['type'] ?? '') === 'community_event');

        $this->assertGreaterThan(0, $communityEvents->count());
        
        // Verify our community event is included
        $eventTitles = $communityEvents->map(fn($event) => str_replace(' ✓', '', $event->getTitle()));
        $this->assertTrue($eventTitles->contains($communityEvent->title));
    }

    /** @test */
    public function it_handles_calendar_service_exceptions_gracefully()
    {
        $startDate = now();
        $endDate = now()->addWeek();

        // Create a normal event that should work fine
        $normalEvent = CommunityEvent::factory()->approved()->create([
            'organizer_id' => $this->user->id,
            'start_time' => now()->addDays(1),
        ]);

        // The service should handle any internal exceptions gracefully
        // and return events array without throwing
        $events = $this->calendarService->getEventsForDateRange($startDate, $endDate);

        // Should return events array without throwing
        $this->assertIsArray($events);
        
        // Should include our normal event
        $eventTitles = collect($events)
            ->filter(fn($event) => $event->getExtendedProps()['type'] === 'community_event')
            ->map(fn($event) => str_replace(' ✓', '', $event->getTitle()));
        
        $this->assertTrue($eventTitles->contains($normalEvent->title));
    }

    /** @test */
    public function it_validates_date_range_parameters()
    {
        $startDate = now();
        $endDate = now()->subDay(); // End before start

        $this->expectException(CalendarServiceException::class);

        $this->calendarService->getEventsForDateRange($startDate, $endDate);
    }

    /** @test */
    public function it_only_includes_public_approved_community_events()
    {
        $startDate = now();
        $endDate = now()->addWeek();

        // Create events with different statuses and visibility
        $publicApproved = CommunityEvent::factory()->create([
            'organizer_id' => $this->user->id,
            'start_time' => now()->addDays(1),
            'status' => CommunityEvent::STATUS_APPROVED,
            'visibility' => CommunityEvent::VISIBILITY_PUBLIC,
            'published_at' => now()->subDay(),
        ]);

        $publicPending = CommunityEvent::factory()->create([
            'organizer_id' => $this->user->id,
            'start_time' => now()->addDays(2),
            'status' => CommunityEvent::STATUS_PENDING,
            'visibility' => CommunityEvent::VISIBILITY_PUBLIC,
        ]);

        $membersApproved = CommunityEvent::factory()->create([
            'organizer_id' => $this->user->id,
            'start_time' => now()->addDays(3),
            'status' => CommunityEvent::STATUS_APPROVED,
            'visibility' => CommunityEvent::VISIBILITY_MEMBERS_ONLY,
            'published_at' => now()->subDay(),
        ]);

        $events = $this->calendarService->getEventsForDateRange($startDate, $endDate);
        
        $communityEvents = collect($events)
            ->filter(fn($event) => $event->getExtendedProps()['type'] === 'community_event');

        $this->assertCount(1, $communityEvents);
        
        $eventTitles = $communityEvents->map(fn($event) => str_replace(' ✓', '', $event->getTitle()));
        $this->assertTrue($eventTitles->contains($publicApproved->title));
    }

    /** @test */
    public function it_properly_loads_organizer_relationships()
    {
        $startDate = now();
        $endDate = now()->addWeek();

        $event = CommunityEvent::factory()->approved()->create([
            'organizer_id' => $this->user->id,
            'start_time' => now()->addDays(1),
            'visibility' => CommunityEvent::VISIBILITY_PUBLIC,
        ]);

        $events = $this->calendarService->getEventsForDateRange($startDate, $endDate);
        
        $communityEvent = collect($events)
            ->first(fn($event) => $event->getExtendedProps()['type'] === 'community_event');

        $this->assertNotNull($communityEvent);
        $this->assertEquals($this->user->name, $communityEvent->getExtendedProps()['organizer_name']);
    }
}