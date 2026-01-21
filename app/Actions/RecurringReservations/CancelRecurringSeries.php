<?php

namespace App\Actions\RecurringReservations;

use App\Enums\ReservationStatus;
use App\Models\Event;
use CorvMC\Support\Models\RecurringSeries;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CancelRecurringSeries
{
    use AsAction;

    /**
     * Cancel entire recurring series and all future instances.
     */
    public function handle(RecurringSeries $series, ?string $reason = null): void
    {
        DB::transaction(function () use ($series, $reason) {
            // Cancel series
            $series->update(['status' => 'cancelled']);

            $modelClass = $series->recurable_type;

            // Cancel all future instances
            if ($modelClass === Reservation::class) {
                $futureInstances = Reservation::where('recurring_series_id', $series->id)
                    ->where('reserved_at', '>', now())
                    ->whereIn('status', [ReservationStatus::Scheduled->value, ReservationStatus::Reserved->value, ReservationStatus::Confirmed->value])
                    ->get();

                foreach ($futureInstances as $reservation) {
                    $reservation->update([
                        'status' => ReservationStatus::Cancelled,
                        'cancellation_reason' => $reason ?? 'Recurring series cancelled',
                    ]);
                }
            } else {
                $futureInstances = Event::where('recurring_series_id', $series->id)
                    ->where('start_datetime', '>', now())
                    ->where('status', 'approved')
                    ->get();

                foreach ($futureInstances as $event) {
                    $event->update([
                        'status' => 'cancelled',
                    ]);
                }
            }
        });
    }
}
