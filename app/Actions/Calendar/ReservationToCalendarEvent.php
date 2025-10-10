<?php

namespace App\Actions\Calendar;

use App\Exceptions\Services\CalendarServiceException;
use App\Models\Reservation;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\Concerns\AsAction;

class ReservationToCalendarEvent
{
    use AsAction;

    /**
     * Convert a reservation to a calendar event with proper permissions and styling.
     */
    public function handle(Reservation $reservation): CalendarEvent
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
}
