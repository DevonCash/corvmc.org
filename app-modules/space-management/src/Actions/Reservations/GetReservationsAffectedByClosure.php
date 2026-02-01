<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use Carbon\Carbon;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Models\Reservation;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetReservationsAffectedByClosure
{
    use AsAction;

    /**
     * Get all active reservations that overlap with a closure period.
     *
     * Unlike GetConflictingReservations (which is optimized for single-day
     * queries with caching), this handles arbitrary date ranges for closures.
     */
    public function handle(Carbon $startsAt, Carbon $endsAt): Collection
    {
        return Reservation::with(['reservable', 'user'])
            ->where('status', '!=', ReservationStatus::Cancelled)
            ->where('reserved_until', '>', $startsAt)
            ->where('reserved_at', '<', $endsAt)
            ->orderBy('reserved_at')
            ->get();
    }
}
