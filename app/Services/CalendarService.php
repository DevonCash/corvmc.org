<?php

namespace App\Services;

use App\Exceptions\Services\CalendarServiceException;
use App\Models\Production;
use App\Models\Reservation;
use App\Models\CommunityEvent;
use App\Models\User;
use Guava\Calendar\ValueObjects\CalendarEvent;

class CalendarService
{
    /**
     * Convert a reservation to a calendar event with proper permissions and styling.
     */
    public function reservationToCalendarEvent(Reservation $reservation): CalendarEvent
    {
        if (!$reservation->exists) {
            throw CalendarServiceException::missingRequiredData('reservation', 'Reservation must be persisted to database');
        }

        if (!$reservation->user) {
            throw CalendarServiceException::missingRequiredData('user', 'Reservation must have an associated user');
        }

        if (!$reservation->reserved_at || !$reservation->reserved_until) {
            throw CalendarServiceException::missingRequiredData('reservation times', 'Reservation must have start and end times');
        }

        if ($reservation->reserved_at >= $reservation->reserved_until) {
            throw CalendarServiceException::invalidDateRange($reservation->reserved_at, $reservation->reserved_until);
        }

        try {
            $currentUser = Auth::user();
            $isOwnReservation = $currentUser && $currentUser->id === $reservation->user_id;
            $canViewDetails = $currentUser && $currentUser->can('view reservations');

            // Determine title and visibility
            $title = $this->getReservationTitle($reservation, $isOwnReservation, $canViewDetails);
            
            // Get status-based color
            $color = $this->getReservationColor($reservation->status);

            // Build extended properties
            $extendedProps = $this->getReservationExtendedProps(
                $reservation,
                $isOwnReservation,
                $canViewDetails
            );

            return CalendarEvent::make($reservation)
                ->model(Reservation::class)
                ->key($reservation->id)
                ->title($title)
                ->start($reservation->reserved_at)
                ->end($reservation->reserved_until)
                ->backgroundColor($color)
                ->textColor('#fff')
                ->extendedProps($extendedProps);
        } catch (\Exception $e) {
            if ($e instanceof CalendarServiceException) {
                throw $e;
            }
            throw CalendarServiceException::eventGenerationFailed(
                'Reservation',
                $reservation->id,
                $e->getMessage()
            );
        }
    }

    /**
     * Convert a production to a calendar event with proper styling.
     */
    public function productionToCalendarEvent(Production $production): CalendarEvent
    {
        if (!$production->exists) {
            throw CalendarServiceException::missingRequiredData('production', 'Production must be persisted to database');
        }

        if (!$production->start_time || !$production->end_time) {
            throw CalendarServiceException::missingRequiredData('production times', 'Production must have start and end times');
        }

        if ($production->start_time >= $production->end_time) {
            throw CalendarServiceException::invalidDateRange($production->start_time, $production->end_time);
        }

        try {
            // Only show productions that use the practice space
            if (!$production->usesPracticeSpace()) {
                return CalendarEvent::make($production)
                    ->title('')
                    ->start($production->start_time->toISOString())
                    ->end($production->end_time->toISOString())
                    ->display('none');
            }

            $title = $this->getProductionTitle($production);
            $color = $this->getProductionColor($production->status);
            $extendedProps = $this->getProductionExtendedProps($production);

            return CalendarEvent::make($production)
                ->title($title)
                ->start($production->start_time)
                ->end($production->end_time)
                ->backgroundColor($color)
                ->textColor('#fff')
                ->extendedProps($extendedProps);
        } catch (\Exception $e) {
            if ($e instanceof CalendarServiceException) {
                throw $e;
            }
            throw CalendarServiceException::eventGenerationFailed(
                'Production',
                $production->id,
                $e->getMessage()
            );
        }
    }

    /**
     * Convert a community event to a calendar event with proper styling.
     */
    public function communityEventToCalendarEvent(CommunityEvent $communityEvent): CalendarEvent
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
     * Get reservation title based on permissions.
     */
    private function getReservationTitle(Reservation $reservation, bool $isOwnReservation, bool $canViewDetails): string
    {
        // Show full details for own reservations or if user has permission
        if ($isOwnReservation || $canViewDetails) {
            $title = $reservation->user->name;

            if ($reservation->is_recurring) {
                $title .= ' (Recurring)';
            }

            if ($reservation->status === 'pending') {
                $title .= ' (Pending)';
            }
        } else {
            // Show limited info for other users' reservations
            $title = 'Reserved';

            if ($reservation->status === 'pending') {
                $title .= ' (Pending)';
            }
        }

        return $title;
    }

    /**
     * Get production title with status indicators.
     */
    private function getProductionTitle(Production $production): string
    {
        $title = $production->title;

        if (!$production->isPublished()) {
            $title .= ' (Draft)';
        }

        return $title;
    }

    /**
     * Get color for reservation status.
     */
    private function getReservationColor(string $status): string
    {
        return match ($status) {
            'confirmed' => '#10b981', // green
            'pending' => '#f59e0b',   // yellow
            'cancelled' => '#ef4444', // red
            default => '#6b7280',     // gray
        };
    }

    /**
     * Get color for production status.
     */
    private function getProductionColor(string $status): string
    {
        return match ($status) {
            'pre-production' => '#8b5cf6', // purple
            'production' => '#3b82f6',     // blue
            'completed' => '#10b981',      // green
            'cancelled' => '#ef4444',      // red
            default => '#6b7280',          // gray
        };
    }

    /**
     * Get extended properties for reservation calendar events.
     */
    private function getReservationExtendedProps(
        Reservation $reservation,
        bool $isOwnReservation,
        bool $canViewDetails
    ): array {
        $extendedProps = [
            'type' => 'reservation',
            'status' => $reservation->status,
            'duration' => $reservation->duration,
            'is_recurring' => $reservation->is_recurring,
        ];

        // Add detailed info only for own reservations or if permitted
        if ($isOwnReservation || $canViewDetails) {
            $extendedProps['user_name'] = $reservation->user->name;
            $extendedProps['cost'] = $reservation->cost;
        }

        return $extendedProps;
    }

    /**
     * Get extended properties for production calendar events.
     */
    private function getProductionExtendedProps(Production $production): array
    {
        return [
            'type' => 'production',
            'manager_name' => $production->manager->name ?? '',
            'status' => $production->status,
            'venue_name' => $production->venue_name,
            'is_published' => $production->isPublished(),
            'ticket_url' => $production->ticket_url,
        ];
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

    /**
     * Get calendar events for a date range.
     */
    public function getEventsForDateRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        if ($start >= $end) {
            throw CalendarServiceException::invalidDateRange($start, $end);
        }

        try {
            $reservations = Reservation::whereBetween('reserved_at', [$start, $end])
                ->with('user')
                ->get();

            $productions = Production::whereBetween('start_time', [$start, $end])
                ->with('manager')
                ->get();

            $communityEvents = CommunityEvent::whereBetween('start_time', [$start, $end])
                ->where('status', CommunityEvent::STATUS_APPROVED)
                ->where('visibility', CommunityEvent::VISIBILITY_PUBLIC)
                ->with('organizer')
                ->get();

            $events = [];

            foreach ($reservations as $reservation) {
                try {
                    $events[] = $this->reservationToCalendarEvent($reservation);
                } catch (CalendarServiceException $e) {
                    // Log the error but continue processing other events
                    \Log::warning("Failed to generate calendar event for reservation {$reservation->id}: {$e->getMessage()}");
                }
            }

            foreach ($productions as $production) {
                try {
                    $events[] = $this->productionToCalendarEvent($production);
                } catch (CalendarServiceException $e) {
                    // Log the error but continue processing other events
                    \Log::warning("Failed to generate calendar event for production {$production->id}: {$e->getMessage()}");
                }
            }

            foreach ($communityEvents as $communityEvent) {
                try {
                    $events[] = $this->communityEventToCalendarEvent($communityEvent);
                } catch (CalendarServiceException $e) {
                    // Log the error but continue processing other events
                    \Log::warning("Failed to generate calendar event for community event {$communityEvent->id}: {$e->getMessage()}");
                }
            }

            return $events;
        } catch (\Exception $e) {
            throw CalendarServiceException::conflictDetectionFailed("Database query failed: {$e->getMessage()}");
        }
    }

    /**
     * Check for scheduling conflicts between events.
     */
    public function hasConflicts(\DateTimeInterface $start, \DateTimeInterface $end, ?int $excludeReservationId = null, ?int $excludeProductionId = null): array
    {
        if ($start >= $end) {
            throw CalendarServiceException::invalidDateRange($start, $end);
        }

        try {
            $conflicts = [];

        // Check reservation conflicts
        $reservationQuery = Reservation::where('status', 'confirmed')
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('reserved_at', [$start, $end])
                    ->orWhereBetween('reserved_until', [$start, $end])
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->where('reserved_at', '<=', $start)
                          ->where('reserved_until', '>=', $end);
                    });
            });

        if ($excludeReservationId) {
            $reservationQuery->where('id', '!=', $excludeReservationId);
        }

        $conflictingReservations = $reservationQuery->with('user')->get();
        foreach ($conflictingReservations as $reservation) {
            $conflicts[] = [
                'type' => 'reservation',
                'title' => "Reservation: {$reservation->user->name}",
                'start' => $reservation->reserved_at,
                'end' => $reservation->reserved_until,
                'model' => $reservation,
            ];
        }

        // Check production conflicts (only those using practice space)
        $productionQuery = Production::where(function ($query) use ($start, $end) {
                $query->whereBetween('start_time', [$start, $end])
                    ->orWhereBetween('end_time', [$start, $end])
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->where('start_time', '<=', $start)
                          ->where('end_time', '>=', $end);
                    });
            })
            ->where(function ($query) {
                $query->whereNull('location->is_external')
                      ->orWhere('location->is_external', false);
            });

        if ($excludeProductionId) {
            $productionQuery->where('id', '!=', $excludeProductionId);
        }

        $conflictingProductions = $productionQuery->get();
        foreach ($conflictingProductions as $production) {
            $conflicts[] = [
                'type' => 'production',
                'title' => "Production: {$production->title}",
                'start' => $production->start_time,
                'end' => $production->end_time,
                'model' => $production,
            ];
        }

            return $conflicts;
        } catch (\Exception $e) {
            throw CalendarServiceException::conflictDetectionFailed("Database query failed: {$e->getMessage()}");
        }
    }
}