<?php

namespace App\Actions\Reservations;

use App\Actions\GoogleCalendar\SyncReservationToGoogleCalendar;
use App\Models\RehearsalReservation;
use App\Models\User;
use App\Notifications\ReservationCreatedNotification;
use App\Notifications\ReservationCreatedTodayNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateReservation
{
    use AsAction;

    /**
     * Create a new reservation.
     *
     * Reservations are created as pending and must be confirmed within the confirmation window.
     * Credits are applied at confirmation time, not creation time.
     * For immediate reservations (< 3 days), auto-confirmation is triggered.
     */
    public function handle(User $user, Carbon $startTime, Carbon $endTime, array $options = []): RehearsalReservation
    {
        // Validate the reservation
        $errors = ValidateReservation::run($user, $startTime, $endTime);

        if (! empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: '.implode(' ', $errors));
        }

        $reservation = DB::transaction(function () use ($user, $startTime, $endTime, $options) {
            // Calculate initial cost estimate (without deducting credits yet)
            $costCalculation = CalculateReservationCost::run($user, $startTime, $endTime);

            // Create reservation as pending
            // Credits will be deducted when reservation is confirmed
            $reservation = RehearsalReservation::create([
                'reservable_type' => User::class,
                'reservable_id' => $user->id,
                'reserved_at' => $startTime,
                'reserved_until' => $endTime,
                'cost' => $costCalculation['cost'],
                'hours_used' => $costCalculation['total_hours'],
                'free_hours_used' => $costCalculation['free_hours'],
                'status' => 'pending',
                'notes' => $options['notes'] ?? null,
                'is_recurring' => $options['is_recurring'] ?? false,
                'recurrence_pattern' => $options['recurrence_pattern'] ?? null,
            ]);

            return $reservation;
        });

        // For immediate reservations (< 3 days), auto-confirm
        $daysUntilReservation = now()->diffInDays($startTime, false);
        if ($daysUntilReservation < 3) {
            $reservation = ConfirmReservation::run($reservation);
        } else {
            // For future reservations, send creation notification
            $user->notify(new ReservationCreatedNotification($reservation));
        }

        // Notify admins if reservation is for today
        if ($startTime->isToday()) {
            $admins = User::role('admin')->get();
            Notification::send($admins, new ReservationCreatedTodayNotification($reservation));
        }

        // Sync to Google Calendar (both pending and confirmed show on calendar)
        SyncReservationToGoogleCalendar::run($reservation, 'create');

        return $reservation;
    }
}
