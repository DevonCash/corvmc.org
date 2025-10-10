<?php

namespace App\Actions\RecurringReservations;

use App\Actions\Reservations\CreateReservation;
use App\Models\RecurringReservation;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateRecurringInstances
{
    use AsAction;

    /**
     * Generate reservation instances for a recurring series.
     * Only generates up to max_advance_days into the future.
     */
    public function handle(RecurringReservation $series): Collection
    {
        $startDate = $series->series_start_date;
        $maxDate = now()->addDays($series->max_advance_days);

        if ($series->series_end_date && $series->series_end_date->lt($maxDate)) {
            $maxDate = $series->series_end_date;
        }

        $occurrences = CalculateOccurrences::run($series->recurrence_rule, $startDate, $maxDate);
        $created = collect();

        foreach ($occurrences as $date) {
            // Check if instance already exists
            $existing = Reservation::where('recurring_reservation_id', $series->id)
                ->where('instance_date', $date->toDateString())
                ->first();

            if ($existing) {
                continue;
            }

            // Try to create the actual reservation
            try {
                $reservation = $this->createInstanceReservation($series, $date);
                $created->push($reservation);
            } catch (\InvalidArgumentException $e) {
                // Conflict - create a placeholder cancelled reservation to track skip
                Reservation::create([
                    'user_id' => $series->user_id,
                    'recurring_reservation_id' => $series->id,
                    'instance_date' => $date->toDateString(),
                    'reserved_at' => $date->copy()->setTimeFromTimeString($series->start_time->format('H:i:s')),
                    'reserved_until' => $date->copy()->setTimeFromTimeString($series->end_time->format('H:i:s')),
                    'status' => 'cancelled',
                    'cancellation_reason' => 'Scheduling conflict',
                    'is_recurring' => true,
                    'cost' => 0,
                ]);
            }
        }

        return $created;
    }

    /**
     * Create a single reservation instance from recurring pattern.
     */
    protected function createInstanceReservation(
        RecurringReservation $series,
        Carbon $date
    ): Reservation {
        $startDateTime = $date->copy()->setTimeFromTimeString($series->start_time->format('H:i:s'));
        $endDateTime = $date->copy()->setTimeFromTimeString($series->end_time->format('H:i:s'));

        return CreateReservation::run(
            $series->user,
            $startDateTime,
            $endDateTime,
            [
                'recurring_reservation_id' => $series->id,
                'instance_date' => $date->toDateString(),
                'is_recurring' => true,
                'recurrence_pattern' => ['source' => 'recurring_series'],
                'status' => 'pending',
            ]
        );
    }
}
