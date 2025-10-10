<?php

namespace App\Actions\RecurringReservations;

use App\Models\RecurringReservation;
use App\Models\User;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateRecurringSeries
{
    use AsAction;

    /**
     * Create a new recurring reservation series.
     *
     * @throws \InvalidArgumentException If user is not a sustaining member
     */
    public function handle(
        User $user,
        string $recurrenceRule,
        Carbon $startDate,
        string $startTime,
        string $endTime,
        ?Carbon $endDate = null,
        int $maxAdvanceDays = 90,
        ?string $notes = null
    ): RecurringReservation {
        if (!$user->isSustainingMember()) {
            throw new \InvalidArgumentException('Only sustaining members can create recurring reservations.');
        }

        // Calculate duration in minutes
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);
        $durationMinutes = $start->diffInMinutes($end);

        $series = RecurringReservation::create([
            'user_id' => $user->id,
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
