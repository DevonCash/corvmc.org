<?php

namespace App\Actions\GoogleCalendar;

use App\Models\Reservation;
use App\Settings\GoogleCalendarSettings;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncReservationToGoogleCalendar
{
    use AsAction;

    /**
     * Sync a reservation to Google Calendar.
     * Creates, updates, or deletes the event based on reservation status.
     */
    public function handle(Reservation $reservation, string $action = 'create'): ?string
    {
        $settings = app(GoogleCalendarSettings::class);

        // Check if sync is enabled
        if (! $settings->enable_google_calendar_sync) {
            return null;
        }

        // Validate settings
        if (! $settings->google_calendar_id) {
            Log::warning('Google Calendar sync enabled but calendar ID not set');

            return null;
        }

        try {
            $calendarService = $this->getCalendarService();
            $calendarId = $settings->google_calendar_id;

            switch ($action) {
                case 'create':
                case 'update':
                    return $this->createOrUpdateEvent($calendarService, $calendarId, $reservation);

                case 'delete':
                    return $this->deleteEvent($calendarService, $calendarId, $reservation);

                default:
                    throw new \InvalidArgumentException("Invalid action: {$action}");
            }
        } catch (\Exception $e) {
            Log::error('Failed to sync reservation to Google Calendar', [
                'reservation_id' => $reservation->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create or update a Google Calendar event.
     */
    protected function createOrUpdateEvent(
        Calendar $calendarService,
        string $calendarId,
        Reservation $reservation
    ): ?string {
        $event = new Event([
            'summary' => $this->getEventTitle($reservation),
            'description' => $this->getEventDescription($reservation),
            'start' => new EventDateTime([
                'dateTime' => $reservation->reserved_at->toRfc3339String(),
                'timeZone' => config('app.timezone'),
            ]),
            'end' => new EventDateTime([
                'dateTime' => $reservation->reserved_until->toRfc3339String(),
                'timeZone' => config('app.timezone'),
            ]),
            'colorId' => $this->getEventColor($reservation),
        ]);

        // If reservation has a Google Calendar event ID, update it
        if ($reservation->google_calendar_event_id) {
            $updatedEvent = $calendarService->events->update(
                $calendarId,
                $reservation->google_calendar_event_id,
                $event
            );

            return $updatedEvent->getId();
        }

        // Otherwise, create a new event
        $createdEvent = $calendarService->events->insert($calendarId, $event);

        // Store the event ID on the reservation
        $reservation->google_calendar_event_id = $createdEvent->getId();
        $reservation->saveQuietly();

        return $createdEvent->getId();
    }

    /**
     * Delete a Google Calendar event.
     */
    protected function deleteEvent(
        Calendar $calendarService,
        string $calendarId,
        Reservation $reservation
    ): ?string {
        if (! $reservation->google_calendar_event_id) {
            return null;
        }

        $calendarService->events->delete(
            $calendarId,
            $reservation->google_calendar_event_id
        );

        $eventId = $reservation->google_calendar_event_id;
        $reservation->google_calendar_event_id = null;
        $reservation->saveQuietly();

        return $eventId;
    }

    /**
     * Get the calendar service instance.
     */
    protected function getCalendarService(): Calendar
    {
        $credentialsPath = config('services.google.calendar_credentials_path');

        if (! $credentialsPath || ! file_exists($credentialsPath)) {
            throw new \RuntimeException('Google Calendar credentials file not found at: '.$credentialsPath);
        }

        $client = new Client;
        $client->setApplicationName(config('app.name'));
        $client->setScopes([Calendar::CALENDAR]);
        $client->setAuthConfig($credentialsPath);

        return new Calendar($client);
    }

    /**
     * Get the event title for a reservation.
     */
    protected function getEventTitle(Reservation $reservation): string
    {
        $reserver = $reservation->getResponsibleUser();
        $title = 'Practice Space - '.$reserver->name;

        if ($reservation->status === 'pending') {
            $title .= ' (Pending)';
        }

        return $title;
    }

    /**
     * Get the event description for a reservation.
     */
    protected function getEventDescription(Reservation $reservation): string
    {
        $reserver = $reservation->getResponsibleUser();
        $description = "Reserved by: {$reserver->name}\n";
        $description .= "Status: {$reservation->status}\n";

        if ($reservation->notes) {
            $description .= "Notes: {$reservation->notes}\n";
        }

        $description .= "\nDuration: {$reservation->hours_used} hours\n";
        $description .= 'Cost: '.$reservation->cost->formatTo('en_US')."\n";

        if ($reservation->free_hours_used > 0) {
            $description .= "Free hours used: {$reservation->free_hours_used}\n";
        }

        return $description;
    }

    /**
     * Get the color ID for a reservation based on its status.
     */
    protected function getEventColor(Reservation $reservation): string
    {
        return match ($reservation->status) {
            'confirmed' => '10', // Green
            'pending' => '5',    // Yellow
            'cancelled' => '11', // Red
            default => '8',      // Gray
        };
    }
}
