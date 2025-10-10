<?php

namespace App\Services;

use App\Data\Reservation\ReservationUsageData;
use App\Models\Production;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationCancelledNotification;
use App\Notifications\ReservationConfirmedNotification;
use App\Notifications\ReservationCreatedNotification;
use App\Facades\MemberBenefitsService;
use Brick\Money\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Spatie\Period\Period;
use Spatie\Period\Precision;

class ReservationService
{
    public const HOURLY_RATE = 15.00;

    public const SUSTAINING_MEMBER_FREE_HOURS = 4;

    public const MIN_RESERVATION_DURATION = 1; // hours

    public const MAX_RESERVATION_DURATION = 8; // hours

    public const MINUTES_PER_BLOCK = 30; // Practice space credits are in 30-minute blocks

    /**
     * Convert hours to blocks for credit system.
     */
    public function hoursToBlocks(float $hours): int
    {
        return (int) ceil(($hours * 60) / self::MINUTES_PER_BLOCK);
    }

    /**
     * Convert blocks to hours for display.
     */
    public function blocksToHours(int $blocks): float
    {
        return ($blocks * self::MINUTES_PER_BLOCK) / 60;
    }


    /**
     * Calculate the cost for a reservation.
     *
     * @deprecated Use \App\Actions\Reservations\CalculateReservationCost instead
     */
    public function calculateCost(User $user, Carbon $startTime, Carbon $endTime): array
    {
        return \App\Actions\Reservations\CalculateReservationCost::run($user, $startTime, $endTime);
    }

    /**
     * Calculate duration in hours between two times using Period.
     */
    public function calculateHours(Carbon $startTime, Carbon $endTime): float
    {
        // Use the original Carbon calculation for consistency with existing behavior
        return $startTime->diffInMinutes($endTime) / 60;
    }

    /**
     * Create a Period from start and end times.
     */
    public function createPeriod(Carbon $startTime, Carbon $endTime): ?Period
    {
        // Period package will throw exception if end is before start
        if ($endTime <= $startTime) {
            return null;
        }

        return Period::make($startTime, $endTime, Precision::MINUTE());
    }

    /**
     * Check if a time slot is available (no conflicts with reservations or productions).
     *
     * @deprecated Use \App\Actions\Reservations\CheckTimeSlotAvailability instead
     */
    public function isTimeSlotAvailable(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): bool
    {
        return \App\Actions\Reservations\CheckTimeSlotAvailability::run($startTime, $endTime, $excludeReservationId);
    }

    /**
     * Get potentially conflicting reservations for a time slot.
     * Uses a broader database query then filters with Period for precision.
     *
     * @deprecated Use \App\Actions\Reservations\GetConflictingReservations instead
     */
    public function getConflictingReservations(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): Collection
    {
        return \App\Actions\Reservations\GetConflictingReservations::run($startTime, $endTime, $excludeReservationId);
    }

    /**
     * Get productions that conflict with a time slot (only those using practice space).
     *
     * @deprecated Use \App\Actions\Reservations\GetConflictingProductions instead
     */
    public function getConflictingProductions(Carbon $startTime, Carbon $endTime): Collection
    {
        return \App\Actions\Reservations\GetConflictingProductions::run($startTime, $endTime);
    }

    /**
     * Get all conflicts (both reservations and productions) for a time slot.
     *
     * @deprecated Use \App\Actions\Reservations\GetAllConflicts instead
     */
    public function getAllConflicts(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): array
    {
        return \App\Actions\Reservations\GetAllConflicts::run($startTime, $endTime, $excludeReservationId);
    }

    /**
     * Check if a time slot has any conflicts (reservations or productions).
     *
     * @deprecated Use \App\Actions\Reservations\CheckTimeSlotAvailability (inverted) instead
     */
    public function hasAnyConflicts(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): bool
    {
        return !\App\Actions\Reservations\CheckTimeSlotAvailability::run($startTime, $endTime, $excludeReservationId);
    }

    /**
     * Validate reservation parameters.
     *
     * @deprecated Use \App\Actions\Reservations\ValidateReservation instead
     */
    public function validateReservation(User $user, Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): array
    {
        return \App\Actions\Reservations\ValidateReservation::run($user, $startTime, $endTime, $excludeReservationId);
    }

    /**
     * Create a new reservation.
     *
     * @deprecated Use \App\Actions\Reservations\CreateReservation instead
     */
    public function createReservation(User $user, Carbon $startTime, Carbon $endTime, array $options = []): \App\Models\RehearsalReservation
    {
        return \App\Actions\Reservations\CreateReservation::run($user, $startTime, $endTime, $options);
    }

    /**
     * Update an existing reservation.
     *
     * @deprecated Use \App\Actions\Reservations\UpdateReservation instead
     */
    public function updateReservation(\App\Models\RehearsalReservation $reservation, Carbon $startTime, Carbon $endTime, array $options = []): \App\Models\RehearsalReservation
    {
        return \App\Actions\Reservations\UpdateReservation::run($reservation, $startTime, $endTime, $options);
    }

    /**
     * Confirm a pending reservation.
     *
     * @deprecated Use \App\Actions\Reservations\ConfirmReservation instead
     */
    public function confirmReservation(\App\Models\RehearsalReservation $reservation): \App\Models\RehearsalReservation
    {
        return \App\Actions\Reservations\ConfirmReservation::run($reservation);
    }

    /**
     * Cancel a reservation.
     *
     * @deprecated Use \App\Actions\Reservations\CancelReservation instead
     */
    public function cancelReservation(\App\Models\RehearsalReservation $reservation, ?string $reason = null): \App\Models\RehearsalReservation
    {
        return \App\Actions\Reservations\CancelReservation::run($reservation, $reason);
    }

    /**
     * Get available time slots for a given date considering both reservations and productions.
     *
     * @deprecated Use \App\Actions\Reservations\GetAvailableTimeSlots instead
     */
    public function getAvailableTimeSlots(Carbon $date, int $durationHours = 1): array
    {
        return \App\Actions\Reservations\GetAvailableTimeSlots::run($date, $durationHours);
    }

    /**
     * Find gaps between reservations and productions for a given date using Period operations.
     *
     * @deprecated Use \App\Actions\Reservations\FindAvailableGaps instead
     */
    public function findAvailableGaps(Carbon $date, int $minimumDurationMinutes = 60): array
    {
        return \App\Actions\Reservations\FindAvailableGaps::run($date, $minimumDurationMinutes);
    }

    /**
     * Create recurring reservations for sustaining members.
     */
    public function createRecurringReservation(User $user, Carbon $startTime, Carbon $endTime, array $recurrencePattern): array
    {
        if (! $user->isSustainingMember()) {
            throw new \InvalidArgumentException('Only sustaining members can create recurring reservations.');
        }

        $reservations = [];
        $weeks = $recurrencePattern['weeks'] ?? 4; // Default to 4 weeks
        $interval = $recurrencePattern['interval'] ?? 1; // Every N weeks

        for ($i = 0; $i < $weeks; $i++) {
            $weekOffset = $i * $interval;
            $recurringStart = $startTime->copy()->addWeeks($weekOffset);
            $recurringEnd = $endTime->copy()->addWeeks($weekOffset);

            try {
                $reservation = $this->createReservation($user, $recurringStart, $recurringEnd, [
                    'is_recurring' => true,
                    'recurrence_pattern' => $recurrencePattern,
                    'status' => 'pending', // Recurring reservations need confirmation
                ]);

                $reservations[] = $reservation;
            } catch (\InvalidArgumentException $e) {
                // Skip this slot if there's a conflict, but continue with others
                continue;
            }
        }

        return $reservations;
    }

    /**
     * Get user's reservation statistics.
     */
    public function getUserStats(User $user): array
    {
        $thisMonth = now()->startOfMonth();
        $thisYear = now()->startOfYear();

        return [
            'total_reservations' => $user->reservations()->count(),
            'this_month_reservations' => $user->reservations()->where('reserved_at', '>=', $thisMonth)->count(),
            'this_year_hours' => $user->reservations()->where('reserved_at', '>=', $thisYear)->sum('hours_used'),
            'this_month_hours' => $user->reservations()->where('reserved_at', '>=', $thisMonth)->sum('hours_used'),
            'free_hours_used' => $user->getUsedFreeHoursThisMonth(),
            'remaining_free_hours' => $user->getRemainingFreeHours(),
            'total_spent' => $user->reservations()->sum('cost'),
            'is_sustaining_member' => $user->isSustainingMember(),
        ];
    }

    /**
     * Get user's reservation usage for a specific month.
     */
    public function getUserUsageForMonth(User $user, Carbon $month): ReservationUsageData
    {
        $reservations = $user->reservations()
            ->whereMonth('reserved_at', $month->month)
            ->whereYear('reserved_at', $month->year)
            ->where('free_hours_used', '>', 0)
            ->get();

        $totalFreeHours = $reservations->sum('free_hours_used');
        $totalHours = $reservations->sum('hours_used');
        $totalPaid = $reservations->sum('cost');

        $allocatedFreeHours = MemberBenefitsService::getUserMonthlyFreeHours($user);

        return new ReservationUsageData(
            month: $month->format('Y-m'),
            total_reservations: $reservations->count(),
            total_hours: $totalHours,
            free_hours_used: $totalFreeHours,
            total_cost: $totalPaid,
            allocated_free_hours: $allocatedFreeHours,
        );
    }

    /**
     * Determine the initial status for a reservation based on business rules.
     *
     * @deprecated Use \App\Actions\Reservations\DetermineReservationStatus instead
     */
    public function determineInitialStatus(Carbon $reservationDate, bool $isRecurring = false): string
    {
        return \App\Actions\Reservations\DetermineReservationStatus::run($reservationDate, $isRecurring);
    }

    /**
     * Check if a reservation needs a confirmation reminder.
     *
     * @deprecated Use \App\Actions\Reservations\DetermineReservationStatus::needsConfirmationReminder instead
     */
    public function needsConfirmationReminder(Carbon $reservationDate, bool $isRecurring = false): bool
    {
        $action = new \App\Actions\Reservations\DetermineReservationStatus();
        return $action->needsConfirmationReminder($reservationDate, $isRecurring);
    }

    /**
     * Calculate when to send the confirmation reminder (1 week before).
     *
     * @deprecated Use \App\Actions\Reservations\DetermineReservationStatus::getConfirmationReminderDate instead
     */
    public function getConfirmationReminderDate(Carbon $reservationDate): Carbon
    {
        $action = new \App\Actions\Reservations\DetermineReservationStatus();
        return $action->getConfirmationReminderDate($reservationDate);
    }

    /**
     * Get all time slots for the practice space (30-minute intervals).
     *
     * @deprecated Use \App\Actions\Reservations\GetAllTimeSlots instead
     */
    public function getAllTimeSlots(): array
    {
        return \App\Actions\Reservations\GetAllTimeSlots::run();
    }

    /**
     * Get valid end time options based on start time (max 8 hours, within business hours).
     *
     * @deprecated Use \App\Actions\Reservations\GetValidEndTimes instead
     */
    public function getValidEndTimes(string $startTime): array
    {
        return \App\Actions\Reservations\GetValidEndTimes::run($startTime);
    }

    /**
     * Get valid end times for a specific date and start time, avoiding conflicts.
     *
     * @deprecated Use \App\Actions\Reservations\GetValidEndTimesForDate instead
     */
    public function getValidEndTimesForDateAndStart(Carbon $date, string $startTime): array
    {
        return \App\Actions\Reservations\GetValidEndTimesForDate::run($date, $startTime);
    }

    /**
     * Validate that a time slot doesn't have conflicts using Spatie Period.
     */
    public function validateTimeSlot(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): array
    {
        $requestedPeriod = $this->createPeriod($startTime, $endTime);

        if (! $requestedPeriod) {
            return [
                'valid' => false,
                'errors' => ['Invalid time period provided'],
            ];
        }

        // Check business hours
        $businessStart = $startTime->copy()->setTime(9, 0);
        $businessEnd = $startTime->copy()->setTime(22, 0);

        if ($startTime->lessThan($businessStart) || $endTime->greaterThan($businessEnd)) {
            return [
                'valid' => false,
                'errors' => ['Reservation must be within business hours (9 AM - 10 PM)'],
            ];
        }

        // Check minimum/maximum duration
        $duration = $startTime->diffInHours($endTime, true);
        if ($duration < self::MIN_RESERVATION_DURATION) {
            return [
                'valid' => false,
                'errors' => ['Minimum reservation duration is ' . self::MIN_RESERVATION_DURATION . ' hour'],
            ];
        }

        if ($duration > self::MAX_RESERVATION_DURATION) {
            return [
                'valid' => false,
                'errors' => ['Maximum reservation duration is ' . self::MAX_RESERVATION_DURATION . ' hours'],
            ];
        }

        // Check for conflicts using existing methods
        $conflicts = $this->getAllConflicts($startTime, $endTime, $excludeReservationId);
        $errors = [];

        if ($conflicts['reservations']->isNotEmpty()) {
            $errors[] = 'Conflicts with ' . $conflicts['reservations']->count() . ' existing reservation(s)';
        }

        if ($conflicts['productions']->isNotEmpty()) {
            $errors[] = 'Conflicts with ' . $conflicts['productions']->count() . ' production(s)';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Get available time slots for a specific date, filtering out conflicted times.
     *
     * @deprecated Use \App\Actions\Reservations\GetAvailableTimeSlotsForDate instead
     */
    public function getAvailableTimeSlotsForDate(Carbon $date): array
    {
        return \App\Actions\Reservations\GetAvailableTimeSlotsForDate::run($date);
    }

    /**
     * Create a Stripe checkout session for a reservation payment.
     *
     * @deprecated Use \App\Actions\Reservations\CreateCheckoutSession instead
     */
    public function createCheckoutSession(\App\Models\RehearsalReservation $reservation)
    {
        return \App\Actions\Reservations\CreateCheckoutSession::run($reservation);
    }

    /**
     * Handle successful payment and update reservation.
     *
     * @deprecated Use \App\Actions\Reservations\HandleSuccessfulPayment instead
     */
    public function handleSuccessfulPayment(\App\Models\RehearsalReservation $reservation, string $sessionId): bool
    {
        return \App\Actions\Reservations\HandleSuccessfulPayment::run($reservation, $sessionId);
    }

    /**
     * Handle failed or cancelled payment.
     *
     * @deprecated Use \App\Actions\Reservations\HandleFailedPayment instead
     */
    public function handleFailedPayment(\App\Models\RehearsalReservation $reservation, ?string $sessionId = null): void
    {
        \App\Actions\Reservations\HandleFailedPayment::run($reservation, $sessionId);
    }
}
