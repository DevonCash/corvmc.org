<?php

namespace App\Actions\RecurringReservations;

use CorvMC\Support\Models\RecurringSeries;
use CorvMC\SpaceManagement\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SkipRecurringInstance
{
    use AsAction;

    /**
     * Skip a single instance without cancelling series.
     */
    public function handle(RecurringSeries $series, Carbon $date, ?string $reason = null): void
    {
        $reservation = Reservation::where('recurring_series_id', $series->id)
            ->where('instance_date', $date->toDateString())
            ->first();

        DB::transaction(function () use ($reservation, $series, $date, $reason) {
            if ($reservation) {
                // Cancel existing reservation
                $reservation->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => $reason ?? 'Manually skipped',
                ]);
            } else {
                // Create placeholder cancelled reservation to track manual skip
                Reservation::create([
                    'user_id' => $series->user_id,
                    'recurring_series_id' => $series->id,
                    'instance_date' => $date->toDateString(),
                    'reserved_at' => $date->copy()->setTimeFromTimeString($series->start_time->format('H:i:s')),
                    'reserved_until' => $date->copy()->setTimeFromTimeString($series->end_time->format('H:i:s')),
                    'status' => 'cancelled',
                    'cancellation_reason' => $reason ?? 'Manually skipped',
                    'is_recurring' => true,
                    'cost' => 0,
                ]);
            }
        });
    }
}
