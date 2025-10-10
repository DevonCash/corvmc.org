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
     *
     * @deprecated Use \App\Actions\Reservations\CreateRecurringReservation instead
     */
    public function createRecurringReservation(User $user, Carbon $startTime, Carbon $endTime, array $recurrencePattern): array
    {
        return \App\Actions\Reservations\CreateRecurringReservation::run($user, $startTime, $endTime, $recurrencePattern);
    }

    /**
     * Get user's reservation statistics.
     *
     * @deprecated Use \App\Actions\Reservations\GetUserStats instead
     */
    public function getUserStats(User $user): array
    {
        return \App\Actions\Reservations\GetUserStats::run($user);
    }

    /**
     * Get user's reservation usage for a specific month.
     *
     * @deprecated Use \App\Actions\Reservations\GetUserUsageForMonth instead
     */
    public function getUserUsageForMonth(User $user, Carbon $month): ReservationUsageData
    {
        return \App\Actions\Reservations\GetUserUsageForMonth::run($user, $month);
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
     *
     * @deprecated Use \App\Actions\Reservations\ValidateTimeSlot instead
     */
    public function validateTimeSlot(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): array
    {
        return \App\Actions\Reservations\ValidateTimeSlot::run($startTime, $endTime, $excludeReservationId);
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
