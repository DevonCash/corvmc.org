<?php

namespace App\Actions\Reservations;

use App\Models\User;
use App\Models\RehearsalReservation;
use App\Models\CreditTransaction;
use App\Notifications\ReservationConfirmedNotification;
use App\Notifications\ReservationCreatedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateReservation
{
    use AsAction;

    /**
     * Create a new reservation.
     */
    public function handle(User $user, Carbon $startTime, Carbon $endTime, array $options = []): RehearsalReservation
    {
        // Validate the reservation
        $errors = ValidateReservation::run($user, $startTime, $endTime);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . implode(' ', $errors));
        }

        // Calculate cost
        $costCalculation = CalculateReservationCost::run($user, $startTime, $endTime);

        return DB::transaction(function () use ($user, $startTime, $endTime, $costCalculation, $options) {
            // Deduct credits if user is using free hours (Credits System integration)
            if ($costCalculation['free_hours'] > 0) {
                $blocks = \App\Actions\Credits\GetBlocksFromHours::run($costCalculation['free_hours']);

                // Check if user has credits in the new system
                $creditsBalance = \App\Actions\Credits\GetBalance::run($user, 'free_hours');

                if ($creditsBalance > 0) {
                    // User is on new Credits System - deduct credits
                    try {
                        \App\Actions\Credits\DeductCredits::run(
                            $user,
                            $blocks,
                            'reservation_usage',
                            null, // Will update with reservation ID after creation
                            'free_hours'
                        );
                    } catch (\App\Exceptions\InsufficientCreditsException $e) {
                        throw new \InvalidArgumentException('Insufficient credits available.');
                    }
                }
                // Otherwise, legacy system will track via free_hours_used field
            }

            $reservation = RehearsalReservation::create([
                'reservable_type' => User::class,
                'reservable_id' => $user->id,
                'reserved_at' => $startTime,
                'reserved_until' => $endTime,
                'cost' => $costCalculation['cost'],
                'hours_used' => $costCalculation['total_hours'],
                'free_hours_used' => $costCalculation['free_hours'],
                'status' => $options['status'] ?? 'confirmed',
                'notes' => $options['notes'] ?? null,
                'is_recurring' => $options['is_recurring'] ?? false,
                'recurrence_pattern' => $options['recurrence_pattern'] ?? null,
            ]);

            // Update the credit transaction with the reservation ID
            if ($costCalculation['free_hours'] > 0 && \App\Actions\Credits\GetBalance::run($user, 'free_hours') >= 0) {
                // Find the most recent deduction transaction and update its source_id
                $latestTransaction = CreditTransaction::where('user_id', $user->id)
                    ->where('credit_type', 'free_hours')
                    ->where('source', 'reservation_usage')
                    ->whereNull('source_id')
                    ->latest('created_at')
                    ->first();

                if ($latestTransaction) {
                    $latestTransaction->update(['source_id' => $reservation->id]);
                }
            }

            // Send appropriate notification based on status
            if ($reservation->status === 'confirmed') {
                // For immediately confirmed reservations, send the confirmation notification
                $user->notify(new ReservationConfirmedNotification($reservation));
            } else {
                // For pending reservations, send the creation notification
                $user->notify(new ReservationCreatedNotification($reservation));
            }

            return $reservation;
        });
    }
}
