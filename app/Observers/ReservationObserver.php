<?php

namespace App\Observers;

use App\Models\Reservation;
use Illuminate\Support\Facades\Cache;

class ReservationObserver
{
    /**
     * Handle the Reservation "created" event.
     */
    public function created(Reservation $reservation): void
    {
        $this->clearReservationCaches($reservation);
    }

    /**
     * Handle the Reservation "updated" event.
     */
    public function updated(Reservation $reservation): void
    {
        $this->clearReservationCaches($reservation);
        
        // If reservation date changed, clear both old and new date caches
        if ($reservation->isDirty('reserved_at')) {
            $originalDate = $reservation->getOriginal('reserved_at');
            if ($originalDate) {
                Cache::forget("reservations.conflicts." . date('Y-m-d', strtotime($originalDate)));
            }
        }
    }

    /**
     * Handle the Reservation "deleted" event.
     */
    public function deleted(Reservation $reservation): void
    {
        $this->clearReservationCaches($reservation);
    }

    /**
     * Clear all caches related to a reservation.
     */
    private function clearReservationCaches(Reservation $reservation): void
    {
        // Clear conflict detection cache for the reservation date
        $date = $reservation->reserved_at ? $reservation->reserved_at->format('Y-m-d') : now()->format('Y-m-d');
        Cache::forget("reservations.conflicts.{$date}");

        // Clear user-specific caches (for RehearsalReservation)
        $responsibleUser = $reservation->getResponsibleUser();
        if ($responsibleUser) {
            $month = $reservation->reserved_at ? $reservation->reserved_at->format('Y-m') : now()->format('Y-m');
            Cache::forget("user.{$responsibleUser->id}.free_hours.{$month}");
            Cache::forget("user_stats.{$responsibleUser->id}");
            Cache::forget("user_activity.{$responsibleUser->id}");
        }
    }
}