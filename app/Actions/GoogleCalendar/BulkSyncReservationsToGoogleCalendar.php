<?php

namespace App\Actions\GoogleCalendar;

use App\Models\Reservation;
use App\Settings\GoogleCalendarSettings;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class BulkSyncReservationsToGoogleCalendar
{
    use AsAction;

    /**
     * Bulk sync all non-cancelled reservations to Google Calendar.
     * Useful for initial migration or recovery.
     *
     * @param  bool  $resyncAll  If true, re-sync even reservations that already have event IDs
     * @return array Statistics about the sync operation
     */
    public function handle(bool $resyncAll = false): array
    {
        $settings = app(GoogleCalendarSettings::class);

        // Check if sync is enabled
        if (! $settings->enable_google_calendar_sync) {
            return [
                'success' => false,
                'message' => 'Google Calendar sync is not enabled',
                'synced' => 0,
                'failed' => 0,
                'skipped' => 0,
            ];
        }

        // Validate settings
        if (! $settings->google_calendar_id) {
            return [
                'success' => false,
                'message' => 'Google Calendar ID not configured',
                'synced' => 0,
                'failed' => 0,
                'skipped' => 0,
            ];
        }

        $query = Reservation::query()
            ->where('status', '!=', 'cancelled')
            ->where('reserved_until', '>=', now()); // Only sync future/current reservations

        if (! $resyncAll) {
            // Only sync reservations that don't have a Google Calendar event ID yet
            $query->whereNull('google_calendar_event_id');
        }

        $reservations = $query->get();
        $synced = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($reservations as $reservation) {
            try {
                // Determine action based on whether event already exists
                $action = $reservation->google_calendar_event_id ? 'update' : 'create';

                $eventId = SyncReservationToGoogleCalendar::run($reservation, $action);

                if ($eventId) {
                    $synced++;
                    Log::info("Bulk sync: Successfully synced reservation #{$reservation->id}");
                } else {
                    $skipped++;
                    Log::warning("Bulk sync: Skipped reservation #{$reservation->id}");
                }
            } catch (\Exception $e) {
                $failed++;
                Log::error("Bulk sync: Failed to sync reservation #{$reservation->id}", [
                    'error' => $e->getMessage(),
                    'reservation_id' => $reservation->id,
                ]);
            }
        }

        $message = "Bulk sync completed: {$synced} synced, {$failed} failed, {$skipped} skipped";
        Log::info($message);

        return [
            'success' => true,
            'message' => $message,
            'synced' => $synced,
            'failed' => $failed,
            'skipped' => $skipped,
            'total' => $reservations->count(),
        ];
    }
}
