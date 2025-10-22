<?php

namespace App\Actions\Reservations;

use App\Enums\CreditType;
use App\Models\User;
use App\Models\RehearsalReservation;
use App\Notifications\ReservationConfirmedNotification;
use App\Notifications\ReservationCreatedNotification;
use Brick\Money\Money;
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

        $reservation = DB::transaction(function () use ($user, $startTime, $endTime, $options) {

            // Calculate cost (automatically applies available free hours)
            $hours = $startTime->diffInMinutes($endTime) / 60;
            $totalBlocks = RehearsalReservation::hoursToBlocks($hours);

            // Get user's free hour credit balance
            $freeBlockBalance = $user->getCreditBalance(CreditType::FreeHours);
            $freeBlocks = min($totalBlocks, $freeBlockBalance);
            $paidBlocks = $totalBlocks - $freeBlocks;

            // Calculate cost: $15/hour = $7.50 per 30-min block
            $hourlyRate = config('reservation.hourly_rate', 15.00);
            $costPerBlock = $hourlyRate / 2;
            $cost = Money::of($costPerBlock, 'USD')->multipliedBy($paidBlocks);

            // Create reservation
            $reservation = RehearsalReservation::create([
                'reservable_type' => User::class,
                'reservable_id' => $user->id,
                'reserved_at' => $startTime,
                'reserved_until' => $endTime,
                'cost' => $cost,
                'hours_used' => $hours,
                'free_hours_used' => RehearsalReservation::blocksToHours($freeBlocks),
                'status' => $cost->isZero() ? 'confirmed' : 'pending',
                'notes' => $options['notes'] ?? null,
                'is_recurring' => $options['is_recurring'] ?? false,
                'recurrence_pattern' => $options['recurrence_pattern'] ?? null,
            ]);

            // Deduct free hours that were applied
            if ($freeBlocks > 0) {
                $user->deductCredit($freeBlocks, CreditType::FreeHours, 'reservation_usage', $reservation->id);
            }

            return $reservation;
        });
        // Send appropriate notification based on status
        if ($reservation->status === 'confirmed') {
            // For immediately confirmed reservations, send the confirmation notification
            $user->notify(new ReservationConfirmedNotification($reservation));
        } else {
            // For pending reservations, send the creation notification
            $user->notify(new ReservationCreatedNotification($reservation));
        }
        return $reservation;
    }
}
