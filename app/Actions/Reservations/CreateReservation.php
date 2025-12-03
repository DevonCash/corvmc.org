<?php

namespace App\Actions\Reservations;

use App\Actions\GoogleCalendar\SyncReservationToGoogleCalendar;
use App\Enums\CreditType;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
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
     * Reservations are created as scheduled, which locks in the time slot and deducts credits immediately.
     * Confirmation is just an acknowledgement that the user hasn't forgotten about their reservation.
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
            // Calculate cost with available credits
            $costCalculation = CalculateReservationCost::run($user, $startTime, $endTime);

            // Deduct credits immediately when scheduling the reservation
            $freeBlocks = Reservation::hoursToBlocks($costCalculation['free_hours']);
            if ($freeBlocks > 0) {
                $user->deductCredit(
                    $freeBlocks,
                    CreditType::FreeHours,
                    'reservation_usage',
                    null // reservation_id will be null until we create it
                );
            }

            // Create reservation as scheduled (time and credits are now locked in)
            $reservation = RehearsalReservation::create([
                'user_id' => $user->id,
                'reservable_type' => User::class,
                'reservable_id' => $user->id,
                'reserved_at' => $startTime,
                'reserved_until' => $endTime,
                'cost' => $costCalculation['cost'],
                'hours_used' => $costCalculation['total_hours'],
                'free_hours_used' => $costCalculation['free_hours'],
                'status' => ReservationStatus::Scheduled,
                'notes' => $options['notes'] ?? null,
                'is_recurring' => $options['is_recurring'] ?? false,
                'recurrence_pattern' => $options['recurrence_pattern'] ?? null,
                'recurring_series_id' => $options['recurring_series_id'] ?? null,
                'instance_date' => $options['instance_date'] ?? null,
            ]);

            // If cost is zero after credit deduction, mark payment as not applicable
            if ($reservation->cost->isZero()) {
                $reservation->update([
                    'payment_status' => \App\Enums\PaymentStatus::NotApplicable,
                ]);
            }

            return $reservation;
        });

        // For immediate reservations (< 3 days), auto-confirm
        $daysUntilReservation = now()->diffInDays($startTime, false);
        if ($daysUntilReservation < 3) {
            $reservation = ConfirmReservation::run($reservation);
        } else {
            // For future reservations, send creation notification
            try {
                $user->notify(new ReservationCreatedNotification($reservation));
            } catch (\Exception $e) {
                \Log::error('Failed to send reservation creation notification', [
                    'reservation_id' => $reservation->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Notify admins if reservation is for today
        if ($startTime->isToday()) {
            try {
                $admins = User::role('admin')->get();
                Notification::send($admins, new ReservationCreatedTodayNotification($reservation));
            } catch (\Exception $e) {
                \Log::error('Failed to send reservation today notification to admins', [
                    'reservation_id' => $reservation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Sync to Google Calendar (both pending and confirmed show on calendar)
        try {
            SyncReservationToGoogleCalendar::run($reservation, 'create');
        } catch (\Exception $e) {
            \Log::error('Failed to sync new reservation to Google Calendar', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $reservation;
    }
}
