<?php

namespace App\Actions\RecurringReservations;

use CorvMC\Events\Actions\CreateEvent;
use App\Actions\Reservations\CreateReservation;
use App\Enums\ReservationStatus;
use App\Models\Event;
use CorvMC\Support\Models\RecurringSeries;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateRecurringInstances
{
    use AsAction;

    /**
     * Generate instances (Reservations or Events) for a recurring series.
     * Only generates up to max_advance_days into the future.
     */
    public function handle(RecurringSeries $series): Collection
    {
        $startDate = $series->series_start_date;
        $maxDate = now()->addDays($series->max_advance_days);

        if ($series->series_end_date && $series->series_end_date->lt($maxDate)) {
            $maxDate = $series->series_end_date;
        }

        $occurrences = CalculateOccurrences::run($series->recurrence_rule, $startDate, $maxDate);
        $created = collect();

        $modelClass = $series->recurable_type;

        foreach ($occurrences as $date) {
            // Check if instance already exists
            $existing = $modelClass::where('recurring_series_id', $series->id)
                ->where('instance_date', $date->toDateString())
                ->first();

            if ($existing) {
                continue;
            }

            // Try to create the actual instance
            try {
                $instance = $this->createInstance($series, $date);
                $created->push($instance);
            } catch (\InvalidArgumentException $e) {
                // Conflict - create a placeholder cancelled instance to track skip
                $this->createCancelledPlaceholder($series, $date);
            }
        }

        return $created;
    }

    /**
     * Create a single instance from recurring pattern.
     */
    protected function createInstance(RecurringSeries $series, Carbon $date): Reservation|Event
    {
        $startDateTime = $date->copy()->setTimeFromTimeString($series->start_time->format('H:i:s'));
        $endDateTime = $date->copy()->setTimeFromTimeString($series->end_time->format('H:i:s'));

        if ($series->recurable_type === Reservation::class) {
            return CreateReservation::run(
                $series->user,
                $startDateTime,
                $endDateTime,
                [
                    'recurring_series_id' => $series->id,
                    'instance_date' => $date->toDateString(),
                    'is_recurring' => true,
                    'recurrence_pattern' => ['source' => 'recurring_series'],
                    'status' => ReservationStatus::Reserved,
                ]
            );
        }

        // Create Event instance
        return CreateEvent::run([
            'organizer_id' => $series->user_id,
            'recurring_series_id' => $series->id,
            'instance_date' => $date->toDateString(),
            'start_time' => $startDateTime,
            'end_time' => $endDateTime,
            'status' => 'approved',
            'published_at' => now(),
        ]);
    }

    /**
     * Create a cancelled placeholder to track a skipped instance.
     */
    protected function createCancelledPlaceholder(RecurringSeries $series, Carbon $date): void
    {
        $startDateTime = $date->copy()->setTimeFromTimeString($series->start_time->format('H:i:s'));
        $endDateTime = $date->copy()->setTimeFromTimeString($series->end_time->format('H:i:s'));

        Reservation::create([
            'user_id' => $series->user_id,
            'type' => $series->recurable_type,
            'recurring_series_id' => $series->id,
            'instance_date' => $date->toDateString(),
            'reserved_at' => $startDateTime,
            'reserved_until' => $endDateTime,
            'status' => ReservationStatus::Cancelled,
            'cancellation_reason' => 'Scheduling conflict',
            'is_recurring' => true,
            'cost' => 0,
        ]);
    }
}
