<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Events\ReservationCreated;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use App\Models\User;
use CorvMC\SpaceManagement\Notifications\ReservationCreatedNotification;
use CorvMC\SpaceManagement\Notifications\ReservationCreatedTodayNotification;
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
     * Reservations can be created with different statuses:
     * - Scheduled: Standard reservation, credits deducted immediately via Finance listener
     * - Reserved: Recurring instance, credits deducted at confirmation
     * - Confirmed: Pre-confirmed reservation
     *
     * For immediate reservations (< 3 days), auto-confirmation is triggered.
     *
     * NOTE: Pricing and credit deduction are handled by Finance module via
     * ReservationCreated event listener. This action only handles scheduling.
     */
    public function handle(User $user, Carbon $startTime, Carbon $endTime, array $options = []): RehearsalReservation
    {
        // Validate the reservation
        $errors = ValidateReservation::run($user, $startTime, $endTime);

        if (! empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: '.implode(' ', $errors));
        }

        $status = $options['status'] ?? ReservationStatus::Scheduled;

        $reservation = DB::transaction(function () use ($user, $startTime, $endTime, $options, $status) {
            // Calculate hours for display/tracking (pricing handled by Finance)
            $hours = $startTime->diffInMinutes($endTime) / 60;

            // Create reservation (scheduling only - no pricing/credit logic)
            $reservation = RehearsalReservation::create([
                'user_id' => $user->id,
                'reservable_type' => User::class,
                'reservable_id' => $user->id,
                'reserved_at' => $startTime,
                'reserved_until' => $endTime,
                'hours_used' => $hours,
                'status' => $status,
                'notes' => $options['notes'] ?? null,
                'is_recurring' => $options['is_recurring'] ?? false,
                'recurrence_pattern' => $options['recurrence_pattern'] ?? null,
                'recurring_series_id' => $options['recurring_series_id'] ?? null,
                'instance_date' => $options['instance_date'] ?? null,
            ]);

            // Fire event for Finance module to create Charge and handle credits
            // Defer credits for Reserved status (deducted at confirmation)
            $deferCredits = $status === ReservationStatus::Reserved;
            ReservationCreated::dispatch($reservation, $deferCredits);

            return $reservation;
        });

        // For immediate reservations (< 3 days), auto-confirm
        // Skip auto-confirmation for Reserved status (recurring instances)
        $daysUntilReservation = now()->diffInDays($startTime, false);
        if ($daysUntilReservation < 3 && $status !== ReservationStatus::Reserved) {
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

        // Google Calendar sync disabled - integration is unused and pending removal
        // TODO: Remove GoogleCalendar references when module migration complete

        return $reservation;
    }
}
