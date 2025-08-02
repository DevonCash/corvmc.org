<?php

namespace App\Services;

use App\Models\Production;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Period\Period;
use Spatie\Period\Precision;

class ReservationService
{
    public const HOURLY_RATE = 15.00;

    public const SUSTAINING_MEMBER_FREE_HOURS = 4;

    public const MIN_RESERVATION_DURATION = 1; // hours

    public const MAX_RESERVATION_DURATION = 8; // hours

    /**
     * Calculate the cost for a reservation.
     */
    public function calculateCost(User $user, Carbon $startTime, Carbon $endTime): array
    {
        $hours = $this->calculateHours($startTime, $endTime);
        $remainingFreeHours = $user->getRemainingFreeHours();

        $freeHours = $user->isSustainingMember() ? min($hours, $remainingFreeHours) : 0;
        $paidHours = max(0, $hours - $freeHours);

        $cost = $paidHours * self::HOURLY_RATE;

        return [
            'total_hours' => $hours,
            'free_hours' => $freeHours,
            'paid_hours' => $paidHours,
            'cost' => $cost,
            'hourly_rate' => self::HOURLY_RATE,
            'is_sustaining_member' => $user->isSustainingMember(),
            'remaining_free_hours' => $remainingFreeHours,
        ];
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
     */
    public function isTimeSlotAvailable(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): bool
    {
        // If we can't create a valid period, the slot is not available
        if (! $this->createPeriod($startTime, $endTime)) {
            return false;
        }

        return ! $this->hasAnyConflicts($startTime, $endTime, $excludeReservationId);
    }

    /**
     * Get potentially conflicting reservations for a time slot.
     * Uses a broader database query then filters with Period for precision.
     */
    public function getConflictingReservations(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): Collection
    {
        $requestedPeriod = $this->createPeriod($startTime, $endTime);

        // Get reservations that might overlap (broader query)
        $query = Reservation::with('user')
            ->where('status', '!=', 'cancelled')
            ->where('reserved_until', '>', $startTime) // End time is after our start
            ->where('reserved_at', '<', $endTime);     // Start time is before our end

        if ($excludeReservationId) {
            $query->where('id', '!=', $excludeReservationId);
        }

        $reservations = $query->get();

        // If we can't create a valid period, return all potentially overlapping reservations
        if (! $requestedPeriod) {
            return $reservations;
        }

        return $reservations->filter(function (Reservation $reservation) use ($requestedPeriod) {
            return $reservation->overlapsWith($requestedPeriod);
        });
    }

    /**
     * Get productions that conflict with a time slot (only those using practice space).
     */
    public function getConflictingProductions(Carbon $startTime, Carbon $endTime): Collection
    {
        $requestedPeriod = $this->createPeriod($startTime, $endTime);

        // Get productions that might overlap and use practice space
        $query = Production::query()
            ->where('end_time', '>', $startTime) // End time is after our start
            ->where('start_time', '<', $endTime); // Start time is before our end

        $productions = $query->get()
            ->filter(function (Production $production) {
                // Only consider productions that use the practice space
                return $production->usesPracticeSpace();
            });

        // If we can't create a valid period, return all potentially overlapping productions
        if (! $requestedPeriod) {
            return $productions;
        }

        return $productions->filter(function (Production $production) use ($requestedPeriod) {
            return $production->overlapsWith($requestedPeriod);
        });
    }

    /**
     * Get all conflicts (both reservations and productions) for a time slot.
     */
    public function getAllConflicts(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): array
    {
        return [
            'reservations' => $this->getConflictingReservations($startTime, $endTime, $excludeReservationId),
            'productions' => $this->getConflictingProductions($startTime, $endTime),
        ];
    }

    /**
     * Check if a time slot has any conflicts (reservations or productions).
     */
    public function hasAnyConflicts(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): bool
    {
        $conflicts = $this->getAllConflicts($startTime, $endTime, $excludeReservationId);

        return $conflicts['reservations']->isNotEmpty() || $conflicts['productions']->isNotEmpty();
    }

    /**
     * Validate reservation parameters.
     */
    public function validateReservation(User $user, Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): array
    {
        $errors = [];

        // Check if start time is in the future
        if ($startTime->isPast()) {
            $errors[] = 'Reservation start time must be in the future.';
        }

        // Check if end time is after start time
        if ($endTime->lte($startTime)) {
            $errors[] = 'End time must be after start time.';
        }

        $hours = $this->calculateHours($startTime, $endTime);

        // Check minimum duration
        if ($hours < self::MIN_RESERVATION_DURATION) {
            $errors[] = 'Minimum reservation duration is '.self::MIN_RESERVATION_DURATION.' hour(s).';
        }

        // Check maximum duration
        if ($hours > self::MAX_RESERVATION_DURATION) {
            $errors[] = 'Maximum reservation duration is '.self::MAX_RESERVATION_DURATION.' hours.';
        }

        // Check for conflicts
        if (! $this->isTimeSlotAvailable($startTime, $endTime, $excludeReservationId)) {
            $allConflicts = $this->getAllConflicts($startTime, $endTime, $excludeReservationId);
            $conflictMessages = [];

            if ($allConflicts['reservations']->isNotEmpty()) {
                $reservationConflicts = $allConflicts['reservations']->map(function ($r) {
                    return $r->user->name.' ('.$r->reserved_at->format('M j, g:i A').' - '.$r->reserved_until->format('g:i A').')';
                })->join(', ');
                $conflictMessages[] = 'existing reservation(s): '.$reservationConflicts;
            }

            if ($allConflicts['productions']->isNotEmpty()) {
                $productionConflicts = $allConflicts['productions']->map(function ($p) {
                    return $p->title.' ('.$p->start_time->format('M j, g:i A').' - '.$p->end_time->format('g:i A').')';
                })->join(', ');
                $conflictMessages[] = 'production(s): '.$productionConflicts;
            }

            $errors[] = 'Time slot conflicts with '.implode(' and ', $conflictMessages);
        }

        // Business hours check (9 AM to 10 PM)
        if ($startTime->hour < 9 || $endTime->hour > 22 || ($endTime->hour == 22 && $endTime->minute > 0)) {
            $errors[] = 'Reservations are only allowed between 9 AM and 10 PM.';
        }

        return $errors;
    }

    /**
     * Create a new reservation.
     */
    public function createReservation(User $user, Carbon $startTime, Carbon $endTime, array $options = []): Reservation
    {
        $errors = $this->validateReservation($user, $startTime, $endTime);

        if (! empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: '.implode(' ', $errors));
        }

        $costCalculation = $this->calculateCost($user, $startTime, $endTime);

        return DB::transaction(function () use ($user, $startTime, $endTime, $costCalculation, $options) {
            return Reservation::create([
                'user_id' => $user->id,
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
        });
    }

    /**
     * Update an existing reservation.
     */
    public function updateReservation(Reservation $reservation, Carbon $startTime, Carbon $endTime, array $options = []): Reservation
    {
        $errors = $this->validateReservation($reservation->user, $startTime, $endTime, $reservation->id);

        if (! empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: '.implode(' ', $errors));
        }

        $costCalculation = $this->calculateCost($reservation->user, $startTime, $endTime);

        return DB::transaction(function () use ($reservation, $startTime, $endTime, $costCalculation, $options) {
            $reservation->update([
                'reserved_at' => $startTime,
                'reserved_until' => $endTime,
                'cost' => $costCalculation['cost'],
                'hours_used' => $costCalculation['total_hours'],
                'free_hours_used' => $costCalculation['free_hours'],
                'notes' => $options['notes'] ?? $reservation->notes,
                'status' => $options['status'] ?? $reservation->status,
            ]);

            return $reservation;
        });
    }

    /**
     * Cancel a reservation.
     */
    public function cancelReservation(Reservation $reservation, ?string $reason = null): Reservation
    {
        $reservation->update([
            'status' => 'cancelled',
            'notes' => $reservation->notes.($reason ? "\nCancellation reason: ".$reason : ''),
        ]);

        return $reservation;
    }

    /**
     * Get available time slots for a given date considering both reservations and productions.
     */
    public function getAvailableTimeSlots(Carbon $date, int $durationHours = 1): array
    {
        $slots = [];
        $startHour = 9; // 9 AM
        $endHour = 22; // 10 PM

        // Get all reservations and productions for the day once to optimize queries
        $dayStart = $date->copy()->setTime(0, 0);
        $dayEnd = $date->copy()->setTime(23, 59);

        $existingReservations = Reservation::with('user')
            ->where('status', '!=', 'cancelled')
            ->where('reserved_until', '>', $dayStart)
            ->where('reserved_at', '<', $dayEnd)
            ->get();

        $existingProductions = Production::query()
            ->where('end_time', '>', $dayStart)
            ->where('start_time', '<', $dayEnd)
            ->get()
            ->filter(function (Production $production) {
                return $production->usesPracticeSpace();
            });

        for ($hour = $startHour; $hour <= $endHour - $durationHours; $hour++) {
            $slotStart = $date->copy()->setTime($hour, 0);
            $slotEnd = $slotStart->copy()->addHours($durationHours);
            $slotPeriod = $this->createPeriod($slotStart, $slotEnd);

            if (! $slotPeriod) {
                continue; // Skip invalid periods
            }

            $hasReservationConflict = $existingReservations->contains(function (Reservation $reservation) use ($slotPeriod) {
                return $reservation->overlapsWith($slotPeriod);
            });

            $hasProductionConflict = $existingProductions->contains(function (Production $production) use ($slotPeriod) {
                return $production->overlapsWith($slotPeriod);
            });

            if (! $hasReservationConflict && ! $hasProductionConflict) {
                $slots[] = [
                    'start' => $slotStart,
                    'end' => $slotEnd,
                    'duration' => $durationHours,
                    'period' => $slotPeriod,
                ];
            }
        }

        return $slots;
    }

    /**
     * Find gaps between reservations and productions for a given date using Period operations.
     */
    public function findAvailableGaps(Carbon $date, int $minimumDurationMinutes = 60): array
    {
        $businessHoursStart = $date->copy()->setTime(9, 0);
        $businessHoursEnd = $date->copy()->setTime(22, 0);
        $businessPeriod = $this->createPeriod($businessHoursStart, $businessHoursEnd);

        // Get all reservations and productions for the day
        $dayStart = $date->copy()->setTime(0, 0);
        $dayEnd = $date->copy()->setTime(23, 59);

        $reservations = Reservation::where('status', '!=', 'cancelled')
            ->where('reserved_until', '>', $dayStart)
            ->where('reserved_at', '<', $dayEnd)
            ->orderBy('reserved_at')
            ->get();

        $productions = Production::where('end_time', '>', $dayStart)
            ->where('start_time', '<', $dayEnd)
            ->orderBy('start_time')
            ->get()
            ->filter(function (Production $production) {
                return $production->usesPracticeSpace();
            });

        // Combine all occupied periods
        $occupiedPeriods = collect();

        // Add reservation periods
        $reservations->each(function (Reservation $reservation) use ($occupiedPeriods) {
            $period = $reservation->getPeriod();
            if ($period) {
                $occupiedPeriods->push($period);
            }
        });

        // Add production periods
        $productions->each(function (Production $production) use ($occupiedPeriods) {
            $period = $production->getPeriod();
            if ($period) {
                $occupiedPeriods->push($period);
            }
        });

        if ($occupiedPeriods->isEmpty()) {
            return [$businessPeriod]; // Entire business day is available
        }

        // Use Period collection to find gaps
        $periodCollection = new \Spatie\Period\PeriodCollection(...$occupiedPeriods->toArray());
        $gaps = $periodCollection->gaps($businessPeriod);

        return collect($gaps)
            ->filter(fn (Period $gap) => $gap->length() >= $minimumDurationMinutes)
            ->values()
            ->toArray();
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
     * Determine the initial status for a reservation based on business rules.
     */
    public function determineInitialStatus(Carbon $reservationDate, bool $isRecurring = false): string
    {
        // Recurring reservations always need manual approval
        if ($isRecurring) {
            return 'pending';
        }

        // Reservations more than a week away need confirmation reminder
        if ($reservationDate->isAfter(Carbon::now()->addWeek())) {
            return 'pending';
        }

        // Near-term reservations are immediately confirmed
        return 'confirmed';
    }

    /**
     * Check if a reservation needs a confirmation reminder.
     */
    public function needsConfirmationReminder(Carbon $reservationDate, bool $isRecurring = false): bool
    {
        return !$isRecurring && $reservationDate->isAfter(Carbon::now()->addWeek());
    }

    /**
     * Calculate when to send the confirmation reminder (1 week before).
     */
    public function getConfirmationReminderDate(Carbon $reservationDate): Carbon
    {
        return $reservationDate->copy()->subWeek();
    }
}
