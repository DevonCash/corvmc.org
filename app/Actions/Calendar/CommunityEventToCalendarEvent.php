<?php

namespace App\Actions\Calendar;

use App\Exceptions\Services\CalendarServiceException;
use App\Models\CommunityEvent;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Lorisleiva\Actions\Concerns\AsAction;

class CommunityEventToCalendarEvent
{
    use AsAction;

    /**
     * Convert a community event to a calendar event with proper styling.
     */
    public function handle(CommunityEvent $communityEvent): CalendarEvent
    {
        if (!$communityEvent->exists) {
            throw CalendarServiceException::missingRequiredData('community event', 'Community event must be persisted to database');
        }

        if (!$communityEvent->start_time) {
            throw CalendarServiceException::missingRequiredData('community event times', 'Community event must have start time');
        }

        if ($communityEvent->end_time && $communityEvent->start_time >= $communityEvent->end_time) {
            throw CalendarServiceException::invalidDateRange($communityEvent->start_time, $communityEvent->end_time);
        }

        try {
            // Only show approved and published events
            if (!$communityEvent->isPublished()) {
                return CalendarEvent::make($communityEvent)
                    ->title('')
                    ->start($communityEvent->start_time->toISOString())
                    ->end(($communityEvent->end_time ?? $communityEvent->start_time->addHours(2))->toISOString())
                    ->display('none');
            }

            $title = $this->getCommunityEventTitle($communityEvent);
            $color = $this->getCommunityEventColor($communityEvent->event_type);
            $extendedProps = $this->getCommunityEventExtendedProps($communityEvent);

            return CalendarEvent::make($communityEvent)
                ->title($title)
                ->start($communityEvent->start_time->toISOString())
                ->end(($communityEvent->end_time ?? $communityEvent->start_time->addHours(2))->toISOString())
                ->backgroundColor($color)
                ->textColor('#fff')
                ->extendedProps($extendedProps);
        } catch (\Exception $e) {
            if ($e instanceof CalendarServiceException) {
                throw $e;
            }
            throw CalendarServiceException::eventGenerationFailed(
                'CommunityEvent',
                $communityEvent->id,
                $e->getMessage()
            );
        }
    }

    /**
     * Get community event title with organizer info.
     */
    private function getCommunityEventTitle(CommunityEvent $communityEvent): string
    {
        $title = $communityEvent->title;

        // Add trust badge indicator for verified organizers
        $badge = $communityEvent->getOrganizerTrustBadge();
        if ($badge) {
            $title .= ' ✓';
        }

        return $title;
    }

    /**
     * Get color for community event type.
     */
    private function getCommunityEventColor(string $eventType): string
    {
        return match ($eventType) {
            CommunityEvent::TYPE_PERFORMANCE => '#8b5cf6',      // purple
            CommunityEvent::TYPE_WORKSHOP => '#059669',         // emerald
            CommunityEvent::TYPE_OPEN_MIC => '#dc2626',         // red
            CommunityEvent::TYPE_COLLABORATIVE_SHOW => '#0891b2', // cyan
            CommunityEvent::TYPE_ALBUM_RELEASE => '#ea580c',    // orange
            default => '#6b7280',                               // gray
        };
    }

    /**
     * Get extended properties for community event calendar events.
     */
    private function getCommunityEventExtendedProps(CommunityEvent $communityEvent): array
    {
        return [
            'type' => 'community_event',
            'organizer_name' => $communityEvent->organizer->name ?? '',
            'event_type' => $communityEvent->event_type,
            'venue_name' => $communityEvent->venue_name,
            'venue_address' => $communityEvent->venue_address,
            'visibility' => $communityEvent->visibility,
            'is_public' => $communityEvent->isPublic(),
            'ticket_url' => $communityEvent->ticket_url,
            'distance_from_corvallis' => $communityEvent->distance_from_corvallis,
            'trust_level' => $communityEvent->getOrganizerTrustLevel(),
        ];
    }
}
