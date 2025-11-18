<?php

namespace App\Actions\Reservations;

use App\Actions\GoogleCalendar\SyncReservationToGoogleCalendar;
use App\Enums\CreditType;
use App\Models\CreditTransaction;
use App\Models\RehearsalReservation;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateReservation
{
    use AsAction;

    /**
     * Update an existing reservation.
     */
    public function handle(Reservation $reservation, Carbon $startTime, Carbon $endTime, array $options = []): Reservation
    {
        // Prevent updating paid/comped reservations unless explicitly allowed via payment_status update
        // This prevents cost changes after payment has been processed
        if ($reservation instanceof RehearsalReservation &&
            ($reservation->payment_status?->isPaid() || $reservation->payment_status?->isComped()) &&
            ! isset($options['payment_status'])) {
            throw new \InvalidArgumentException('Cannot update paid or comped reservations. Please cancel and create a new reservation, or update via admin panel.');
        }

        // Only validate and recalculate costs for rehearsal reservations
        if ($reservation instanceof RehearsalReservation) {
            $errors = ValidateReservation::run($reservation->user, $startTime, $endTime, $reservation->id);

            if (! empty($errors)) {
                throw new \InvalidArgumentException('Validation failed: '.implode(' ', $errors));
            }

            $costCalculation = CalculateReservationCost::run($reservation->user, $startTime, $endTime);
        } else {
            // For production reservations, just set basic fields
            $costCalculation = [
                'cost' => $reservation->cost,
                'total_hours' => $startTime->diffInHours($endTime),
                'free_hours' => 0,
            ];
        }

        return DB::transaction(function () use ($reservation, $startTime, $endTime, $costCalculation, $options) {
            $updateData = [
                'reserved_at' => $startTime,
                'reserved_until' => $endTime,
                'notes' => $options['notes'] ?? $reservation->notes,
                'status' => $options['status'] ?? $reservation->status,
            ];

            // Only update cost-related fields for rehearsal reservations
            if ($reservation instanceof RehearsalReservation) {
                $newFreeHours = $costCalculation['free_hours'];
                $newBlocks = Reservation::hoursToBlocks($newFreeHours);

                $updateData['cost'] = $costCalculation['cost'];
                $updateData['hours_used'] = $costCalculation['total_hours'];
                $updateData['free_hours_used'] = $newFreeHours;

                if (isset($options['payment_status'])) {
                    $updateData['payment_status'] = $options['payment_status'];
                }

                // Adjust credits if free hours changed
                // Use original transaction blocks instead of recalculating from hours to avoid precision loss
                $user = $reservation->getResponsibleUser();
                if ($user) {
                    // Find the original deduction transaction to get exact blocks deducted
                    $originalDeduction = CreditTransaction::where('user_id', $user->id)
                        ->where('credit_type', CreditType::FreeHours->value)
                        ->whereIn('source', ['reservation_usage', 'reservation_update'])
                        ->where('source_id', $reservation->id)
                        ->where('amount', '<', 0)
                        ->latest('created_at')
                        ->first();

                    $oldBlocks = $originalDeduction ? abs($originalDeduction->amount) : 0;
                    $blocksDifference = $newBlocks - $oldBlocks;

                    if ($blocksDifference > 0) {
                        // Need to deduct more credits
                        $user->deductCredit(
                            $blocksDifference,
                            CreditType::FreeHours,
                            'reservation_update',
                            $reservation->id
                        );
                    } elseif ($blocksDifference < 0) {
                        // Refund credits
                        $user->addCredit(
                            abs($blocksDifference),
                            CreditType::FreeHours,
                            'reservation_update',
                            $reservation->id,
                            'Refund from reservation update'
                        );
                    }
                }
            }

            $reservation->update($updateData);

            // Sync to Google Calendar (both pending and confirmed)
            SyncReservationToGoogleCalendar::run($reservation->fresh(), 'update');

            return $reservation;
        });
    }
}
