<?php

namespace App\Actions\RecurringReservations;

use App\Models\Event;
use App\Models\RecurringSeries;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateRecurringSeries
{
    use AsAction;

    /**
     * Create a new recurring series.
     *
     * @param  string  $recurableType  The model class this series creates (Reservation::class or Event::class)
     *
     * @throws \InvalidArgumentException If user is not a sustaining member (for reservations)
     */
    public function handle(
        User $user,
        string $recurableType,
        string $recurrenceRule,
        Carbon $startDate,
        string $startTime,
        string $endTime,
        ?Carbon $endDate = null,
        int $maxAdvanceDays = 90,
        ?string $notes = null
    ): RecurringSeries {
        // Only require sustaining membership for recurring reservations
        if ($recurableType === Reservation::class && ! $user->isSustainingMember()) {
            throw new \InvalidArgumentException('Only sustaining members can create recurring reservations.');
        }

        // Calculate duration in minutes
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);
        $durationMinutes = $start->diffInMinutes($end);

        $series = RecurringSeries::create([
            'user_id' => $user->id,
            'recurable_type' => $recurableType,
            'recurrence_rule' => $recurrenceRule,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => $durationMinutes,
            'series_start_date' => $startDate,
            'series_end_date' => $endDate,
            'max_advance_days' => $maxAdvanceDays,
            'status' => 'active',
            'notes' => $notes,
        ]);

        // Generate initial instances
        GenerateRecurringInstances::run($series);

        return $series->fresh();
    }
}
