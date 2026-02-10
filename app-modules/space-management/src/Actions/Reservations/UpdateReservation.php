<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use CorvMC\Finance\Enums\ChargeStatus;
use CorvMC\SpaceManagement\Events\ReservationUpdated;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateReservation
{
    use AsAction;

    /**
     * Update an existing reservation.
     *
     * NOTE: Credit adjustments are handled by Finance module via
     * ReservationUpdated event listener. This action only handles scheduling changes.
     */
    public function handle(Reservation $reservation, Carbon $startTime, Carbon $endTime, array $options = []): Reservation
    {
        // Prevent updating paid/comped reservations unless explicitly allowed via payment_status update
        // Check the Charge model for payment status
        if ($reservation instanceof RehearsalReservation) {
            $chargeStatus = $reservation->charge?->status;
            if ($chargeStatus && $chargeStatus->isSettled()
                && $chargeStatus !== ChargeStatus::Paid
                && $chargeStatus !== ChargeStatus::CoveredByCredits) {
                throw new \InvalidArgumentException('Cannot update comped or refunded reservations. Please cancel and create a new reservation, or update via admin panel.');
            }
        }

        // Only validate for rehearsal reservations
        if ($reservation instanceof RehearsalReservation) {
            $errors = ValidateReservation::run($reservation->getResponsibleUser(), $startTime, $endTime, $reservation->id);

            if (! empty($errors)) {
                throw new \InvalidArgumentException('Validation failed: '.implode(' ', $errors));
            }
        }

        // Capture old billable units before update for credit adjustment
        $oldBillableUnits = $reservation instanceof RehearsalReservation
            ? $reservation->getBillableUnits()
            : 0;

        return DB::transaction(function () use ($reservation, $startTime, $endTime, $options, $oldBillableUnits) {
            $hours = $startTime->diffInMinutes($endTime) / 60;

            $updateData = [
                'reserved_at' => $startTime,
                'reserved_until' => $endTime,
                'hours_used' => $hours,
                'notes' => $options['notes'] ?? $reservation->notes,
                'status' => $options['status'] ?? $reservation->status,
            ];

            $reservation->update($updateData);

            // Fire event for Finance module to handle credit adjustments
            if ($reservation instanceof RehearsalReservation) {
                ReservationUpdated::dispatch($reservation, $oldBillableUnits);
            }

            return $reservation;
        });
    }
}
