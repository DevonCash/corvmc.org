<?php

namespace CorvMC\SpaceManagement\Services;

use App\Models\User;
use App\Settings\ReservationSettings;
use Carbon\Carbon;
use CorvMC\SpaceManagement\Services\ConflictData;
use CorvMC\SpaceManagement\Data\ReservationUsageData;
use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\Models\SpaceClosure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use CorvMC\SpaceManagement\Rules\NoReservationOverlap;
use CorvMC\SpaceManagement\Rules\NoClosureOverlap;
use CorvMC\SpaceManagement\Rules\WithinBusinessHours;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

class ReservationService
{
    /**
     * Unified conflict checking method.
     *
     * @param Carbon $startTime Start time to check
     * @param Carbon $endTime End time to check
     * @param int|null $excludeId Reservation ID to exclude
     * @param bool $includeBuffer Apply buffer time (default: true)
     * @param bool $includeClosures Check closures (default: true)
     * @param bool $returnData Return ConflictData object vs Collection (default: false)
     * @return ConflictData|Collection|array
     */
    public function getConflicts(
        Carbon $startTime,
        Carbon $endTime,
        ?int $excludeId = null,
        bool $includeBuffer = true,
        bool $includeClosures = true,
        bool $returnData = false
    ) {
        $bufferMinutes = $includeBuffer ? app(ReservationSettings::class)->buffer_minutes : 0;
        $bufferedStart = $startTime->copy()->subMinutes($bufferMinutes);
        $bufferedEnd = $endTime->copy()->addMinutes($bufferMinutes);

        // Get ALL reservations (includes RehearsalReservation and EventReservation via STI)
        $reservations = $this->getConflictingReservationsInternal($bufferedStart, $bufferedEnd, $excludeId);

        // Get closures if requested
        $closures = $includeClosures
            ? $this->getConflictingClosuresInternal($bufferedStart, $bufferedEnd)
            : collect();

        if ($returnData) {
            // ConflictData constructor expects productions - pass empty collection
            return new ConflictData($reservations, collect(), $bufferMinutes);
        }

        // Return as array for backwards compatibility
        if ($includeClosures) {
            return $reservations->merge($closures)
                ->sortBy(fn($item): string => $item->reserved_at ?? $item->starts_at);
        }

        // Simple Collection for checkForConflicts compatibility
        return $reservations;
    }

    /**
     * Find gaps between reservations for a given date using Period operations.
     */
    public function findAvailableGaps(Carbon $date, int $minimumDurationMinutes = 60): array
    {
        $businessHoursStart = $date->copy()->setTime(9, 0);
        $businessHoursEnd = $date->copy()->setTime(22, 0);
        $businessPeriod = Period::make($businessHoursStart, $businessHoursEnd, Precision::MINUTE());

        // Get all reservations for the day
        $dayStart = $date->copy()->setTime(0, 0);
        $dayEnd = $date->copy()->setTime(23, 59);

        $reservations = Reservation::inRange($dayStart, $dayEnd)
            ->orderBy('reserved_at')
            ->get();

        // Combine all occupied periods
        $occupiedPeriods = collect();

        // Add reservation periods
        $reservations->each(function (Reservation $reservation) use ($occupiedPeriods) {
            if (method_exists($reservation, 'getPeriod')) {
                $period = $reservation->getPeriod();
                if ($period) {
                    $occupiedPeriods->push($period);
                }
            }
        });

        if ($occupiedPeriods->isEmpty()) {
            return [$businessPeriod]; // Entire business day is available
        }

        // Use Period collection to find gaps
        $periodCollection = new PeriodCollection(...$occupiedPeriods->toArray());
        $gaps = $periodCollection->gaps();

        return collect($gaps)
            ->filter(fn(Period $gap) => $gap->length() >= $minimumDurationMinutes)
            ->values()
            ->toArray();
    }

    /**
     * Get all time slots for the practice space (30-minute intervals).
     */
    public function getAllTimeSlots(): array
    {
        $slots = [];

        // Practice space hours: 9 AM to 10 PM
        $start = Carbon::createFromTime(9, 0);
        $end = Carbon::createFromTime(22, 0);

        $current = $start->copy();
        while ($current->lessThanOrEqualTo($end)) {
            $timeString = $current->format('H:i');
            $slots[$timeString] = $current->format('g:i A');
            $current->addMinutes(30); // 30-minute intervals
        }

        return $slots;
    }

    /**
     * Get available time slots for a given date considering all reservations.
     */
    public function getAvailableTimeSlots(Carbon $date, int $durationHours = 1): array
    {
        $slots = [];
        $startHour = 9; // 9 AM
        $endHour = 22; // 10 PM

        // Get all reservations for the day once to optimize queries
        $dayStart = $date->copy()->setTime(0, 0);
        $dayEnd = $date->copy()->setTime(23, 59);

        $existingReservations = Reservation::with('reservable')
            ->inRange($dayStart, $dayEnd)
            ->get();

        for ($hour = $startHour; $hour <= $endHour - $durationHours; $hour++) {
            $slotStart = $date->copy()->setTime($hour, 0);
            $slotEnd = $slotStart->copy()->addHours($durationHours);
            $slotPeriod = Period::make($slotStart, $slotEnd, Precision::MINUTE());

            $hasReservationConflict = $existingReservations->contains(function (Reservation $reservation) use ($slotPeriod) {
                return $reservation->overlapsWith($slotPeriod);
            });

            if (! $hasReservationConflict) {
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
     * Get available time slots for a specific date, filtering out conflicted and past times.
     */
    public function getAvailableTimeSlotsForDate(Carbon $date, ?ConflictData $conflicts = null): array
    {
        // Fetch conflicts once if not provided
        $conflicts ??= $this->getConflicts(
            $date->copy()->startOfDay(),
            $date->copy()->endOfDay(),
            returnData: true
        );

        $allSlots = $this->getAllTimeSlots();
        $availableSlots = [];

        foreach ($allSlots as $timeString => $label) {
            $testStart = $date->copy()->setTimeFromTimeString($timeString);
            $testEnd = $testStart->copy()->addHour(); // Test with 1 hour duration

            // Only check for conflicts and past times, not duration limits
            $hasConflicts = $conflicts->hasConflict($testStart, $testEnd);
            $isPast = $testStart->isPast();

            // Only include slots that don't have conflicts and are in the future
            if (! $hasConflicts && ! $isPast) {
                $availableSlots[$timeString] = $label;
            }
        }

        return $availableSlots;
    }

    /**
     * Internal helper to get conflicting reservations.
     */
    private function getConflictingReservationsInternal(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): Collection
    {
        // Simple database query - let the DB do the rectangle select
        $query = Reservation::with('reservable')
            ->where('ends_at', '>', $startTime)
            ->where('reserved_at', '<', $endTime)
            ->when($excludeReservationId, function ($q) use ($excludeReservationId) {
                $q->where('id', '!=', $excludeReservationId);
            });

        return $query->get();
    }


    /**
     * Internal helper to get conflicting closures.
     */
    private function getConflictingClosuresInternal(Carbon $startTime, Carbon $endTime): Collection
    {
        // Simple database query - let the DB do the rectangle select
        $query = SpaceClosure::query()
            ->where('ends_at', '>', $startTime)
            ->where('starts_at', '<', $endTime);

        return $query->get();
    }

    /**
     * Get user's reservation statistics.
     */
    public function getUserStats(User $user): array
    {
        $thisMonth = now()->startOfMonth();
        $thisYear = now()->startOfYear();

        return [
            'total_reservations' => $user->rehearsals()->count(),
            'this_month_reservations' => $user->rehearsals()->where('reserved_at', '>=', $thisMonth)->count(),
            'this_year_hours' => $user->rehearsals()->where('reserved_at', '>=', $thisYear)->sum('hours_used'),
            'this_month_hours' => $user->rehearsals()->where('reserved_at', '>=', $thisMonth)->sum('hours_used'),
            'free_hours_used' => $user->getUsedFreeHoursThisMonth(),
            'remaining_free_hours' => $user->getRemainingFreeHours(),
            'total_spent' => $user->rehearsals()->sum('cost'),
            'is_sustaining_member' => $user->isSustainingMember(),
        ];
    }

    /**
     * Get user's reservation usage for a specific month.
     */
    public function getUserUsageForMonth(User $user, Carbon $month): ReservationUsageData
    {
        $reservations = $user->rehearsals()
            ->whereMonth('reserved_at', $month->month)
            ->whereYear('reserved_at', $month->year)
            ->where('free_hours_used', '>', 0)
            ->get();

        $totalFreeHours = $reservations->sum('free_hours_used');
        $totalHours = $reservations->sum('hours_used');
        $totalPaid = $reservations->sum('cost');

        $allocatedFreeHours = MemberBenefitService::getUserMonthlyFreeHours($user);

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
     * Get valid end time options based on start time.
     */
    public function getValidEndTimes(string $startTime, ?Carbon $date = null): array
    {
        $date ??= Carbon::now();
        $slots = [];
        $start = $date->copy()->setTimeFromTimeString($startTime);

        // Minimum 1 hour, maximum 8 hours
        $earliestEnd = $start->copy()->addHour();
        $latestEnd = $start->copy()->addHours(8); // MAX_RESERVATION_DURATION

        // Don't go past 10 PM
        $businessEnd = $date->copy()->setTime(22, 0);
        if ($latestEnd->greaterThan($businessEnd)) {
            $latestEnd = $businessEnd;
        }

        $current = $earliestEnd->copy();
        while ($current->lessThanOrEqualTo($latestEnd)) {
            $timeString = $current->format('H:i');
            $slots[$timeString] = $current->format('g:i A');
            $current->addMinutes(30); // MINUTES_PER_BLOCK
        }

        return $slots;
    }

    /**
     * Get valid end times for a specific date and start time, avoiding conflicts.
     */
    public function getValidEndTimesForDate(Carbon $date, string $startTime, ?ConflictData $conflicts = null): array
    {
        // Fetch conflicts once if not provided
        $conflicts ??= $this->getConflicts(
            $date->copy()->startOfDay(),
            $date->copy()->endOfDay(),
            returnData: true
        );

        $slots = [];
        $start = $date->copy()->setTimeFromTimeString($startTime);

        // Minimum 1 hour, maximum 8 hours
        $earliestEnd = $start->copy()->addHour();
        $latestEnd = $start->copy()->addHours(8); // MAX_RESERVATION_DURATION

        // Don't go past 10 PM
        $businessEnd = $date->copy()->setTime(22, 0);
        if ($latestEnd->greaterThan($businessEnd)) {
            $latestEnd = $businessEnd;
        }

        $current = $earliestEnd->copy();
        while ($current->lessThanOrEqualTo($latestEnd)) {
            $timeString = $current->format('H:i');

            // Check if this end time would cause conflicts
            $hasConflicts = $conflicts->hasConflict($start, $current);

            if (! $hasConflicts) {
                $slots[$timeString] = $current->format('g:i A');
            }

            $current->addMinutes(30); // MINUTES_PER_BLOCK
        }

        return $slots;
    }


    /**
     * Get validation rules for reservation.
     */
    protected function getValidationRules(array $options = []): array
    {
        $checkBusinessHours = $options['checkBusinessHours'] ?? true;
        $checkConflicts = $options['checkConflicts'] ?? true;
        $checkClosures = $options['checkClosures'] ?? true;
        $excludeId = $options['excludeId'] ?? null;

        $rules = [
            'start_time' => [
                'required',
                'date',
                'after:now',
                function ($attribute, $value, $fail) {
                    $startTime = Carbon::parse($value);
                    if ($startTime->isToday()) {
                        $fail('Same-day reservations are not allowed. Please schedule for tomorrow or later.');
                    }
                },
            ],
            'end_time' => [
                'required',
                'date',
                'after:start_time',
            ],
            'hours' => [
                'numeric',
                'min:1',
                'max:8',
            ],
        ];

        // Time slot validation rules (applied to the time_slot composite field)
        $timeSlotRules = [];

        // Business hours validation
        if ($checkBusinessHours) {
            $timeSlotRules[] = new WithinBusinessHours(9, 22);
        }

        // Reservation overlap checking
        if ($checkConflicts) {
            $timeSlotRules[] = new NoReservationOverlap($excludeId, true);
        }

        // Closure overlap checking
        if ($checkClosures) {
            $timeSlotRules[] = new NoClosureOverlap();
        }

        // Only add time_slot rules if we have any
        if (!empty($timeSlotRules)) {
            $rules['time_slot'] = $timeSlotRules;
        }

        return $rules;
    }

    /**
     * Get custom validation messages.
     */
    protected function getValidationMessages(): array
    {
        return [
            'start_time.required' => 'Reservation start time is required.',
            'start_time.date' => 'Start time must be a valid date.',
            'start_time.after' => 'Reservation start time must be in the future.',
            'end_time.required' => 'Reservation end time is required.',
            'end_time.date' => 'End time must be a valid date.',
            'end_time.after' => 'End time must be after start time.',
            'hours.numeric' => 'Duration must be a number.',
            'hours.min' => 'Minimum reservation duration is 1 hour.',
            'hours.max' => 'Maximum reservation duration is 8 hours.',
        ];
    }
}
