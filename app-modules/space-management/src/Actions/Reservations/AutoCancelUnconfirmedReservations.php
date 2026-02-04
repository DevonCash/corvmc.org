<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Notifications\ReservationAutoCancelledNotification;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class AutoCancelUnconfirmedReservations
{
    use AsAction;

    /**
     * Auto-cancel Reserved instances that are within 3 days and haven't been confirmed.
     *
     * This runs daily at 9 AM to enforce the 3-day confirmation requirement.
     * Reserved instances must be confirmed or they will be cancelled to free up the slot.
     */
    public function handle(): Collection
    {
        $threeDaysFromNow = now()->addDays(3);

        // Find all Reserved instances that are within 3 days
        $reservationsToCancel = RehearsalReservation::where('status', ReservationStatus::Reserved)
            ->where('reserved_at', '<=', $threeDaysFromNow)
            ->where('reserved_at', '>', now())
            ->get();

        $cancelled = collect();

        foreach ($reservationsToCancel as $reservation) {
            try {
                $user = $reservation->getResponsibleUser();

                $reservation->update([
                    'status' => ReservationStatus::Cancelled,
                    'cancellation_reason' => 'Not confirmed within 3-day window',
                ]);

                activity('reservation')
                    ->performedOn($reservation)
                    ->event('auto_cancelled')
                    ->withProperties([
                        'original_status' => ReservationStatus::Reserved->value,
                    ])
                    ->log('Reservation auto-cancelled: not confirmed within 3-day window');

                // Send notification to user
                try {
                    $user->notify(new ReservationAutoCancelledNotification($reservation));
                } catch (\Exception $e) {
                    \Log::error('Failed to send auto-cancel notification', [
                        'reservation_id' => $reservation->id,
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $cancelled->push($reservation);

                \Log::info('Auto-cancelled unconfirmed reservation', [
                    'reservation_id' => $reservation->id,
                    'user_id' => $user->id,
                    'reserved_at' => $reservation->reserved_at,
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to auto-cancel reservation', [
                    'reservation_id' => $reservation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $cancelled;
    }
}
