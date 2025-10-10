<?php

namespace App\Actions\RecurringReservations;

use App\Models\RecurringReservation;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CancelRecurringSeries
{
    use AsAction;

    /**
     * Cancel entire recurring series and all future instances.
     */
    public function handle(RecurringReservation $series, ?string $reason = null): void
    {
        DB::transaction(function () use ($series, $reason) {
            // Cancel series
            $series->update(['status' => 'cancelled']);

            // Cancel all future instances
            $futureReservations = Reservation::where('recurring_reservation_id', $series->id)
                ->where('reserved_at', '>', now())
                ->whereIn('status', ['pending', 'confirmed'])
                ->get();

            foreach ($futureReservations as $reservation) {
                $reservation->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => $reason ?? 'Recurring series cancelled',
                ]);
            }
        });
    }
}
