<?php

namespace CorvMC\SpaceManagement\Services;

use App\Models\User;
use App\Settings\ReservationSettings;
use Carbon\Carbon;
use CorvMC\SpaceManagement\Services\ConflictData;
use CorvMC\SpaceManagement\Data\CreateReservationData;
use CorvMC\SpaceManagement\Data\ReservationUsageData;
use CorvMC\SpaceManagement\Data\UpdateReservationData;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Events\ReservationCreated;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
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

/**
 * Service class for managing space reservations.
 *
 * This service handles reservation lifecycle and space management logic.
 * Authorization is handled by policies.
 * Payment concerns are handled by the Finance module's PaymentService.
 */
class ReservationService
{
    /**
     * Create a new reservation.
     * Delegates to the model and logs activity.
     */
    public function create(CreateReservationData $data): RehearsalReservation
    {
        $reservation = RehearsalReservation::createFromData($data);

        // Log activity outside transaction so it doesn't cause creation to fail
        $this->logActivity('created', $reservation, $data->getResponsibleUser(), [
            'hours' => $reservation->hours_used,
            'status' => $reservation->status,
        ]);

        return $reservation;
    }

    /**
     * Update an existing reservation.
     * Delegates to the model and logs activity.
     */
    public function update(Reservation $reservation, UpdateReservationData $data): Reservation
    {
        // Collect the updates for logging
        $originalValues = [
            'reserved_at' => $reservation->reserved_at,
            'reserved_until' => $reservation->reserved_until,
            'notes' => $reservation->notes,
            'status' => $reservation->status,
        ];

        // Update the reservation
        $reservation = $reservation->updateFromData($data);

        // Determine what changed for logging
        $updates = [];
        if ($reservation->reserved_at != $originalValues['reserved_at']) {
            $updates['reserved_at'] = $reservation->reserved_at;
        }
        if ($reservation->reserved_until != $originalValues['reserved_until']) {
            $updates['reserved_until'] = $reservation->reserved_until;
        }
        if ($reservation->notes != $originalValues['notes']) {
            $updates['notes'] = $reservation->notes;
        }
        if ($reservation->status != $originalValues['status']) {
            $updates['status'] = $reservation->status;
        }

        // Log activity outside transaction
        if (!empty($updates)) {
            $this->logActivity('updated', $reservation, User::me(), $updates);
        }

        return $reservation;
    }




    /**
     * Unified conflict checking method.
     *
     * @param Carbon $startTime Start time to check
     * @param Carbon $endTime End time to check
     * @param array $options Options:
     *   - excludeId: Reservation ID to exclude
     *   - includeBuffer: Apply buffer time (default: true)
     *   - includeClosures: Check closures (default: true)
     *   - returnData: Return ConflictData object vs Collection (default: false)
     * @return ConflictData|Collection|array
     */
    public function getConflicts(Carbon $startTime, Carbon $endTime, array $options = [])
    {
        $excludeId = $options['excludeId'] ?? null;
        $includeBuffer = $options['includeBuffer'] ?? true;
        $includeClosures = $options['includeClosures'] ?? true;
        $returnData = $options['returnData'] ?? false;

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
            return [
                'reservations' => $reservations,
                'closures' => $closures,
            ];
        }

        // Simple Collection for checkForConflicts compatibility
        return $reservations;
    }

    /**
     * Legacy method - redirects to unified getConflicts.
     * @deprecated Use getConflicts() instead
     */
    public function checkForConflicts(
        Carbon $startTime,
        Carbon $endTime,
        ?Reservation $excludeReservation = null
    ): Collection {
        return $this->getConflicts($startTime, $endTime, [
            'excludeId' => $excludeReservation?->id,
            'includeBuffer' => false,
            'includeProductions' => false,
            'includeClosures' => false,
        ]);
    }

    /**
     * Get availability calendar for a date range.
     */
    public function getAvailabilityCalendar(Carbon $from, Carbon $to): array
    {
        $reservations = Reservation::overlappingPeriod($from, $to)
            ->get();

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'reservations' => $reservations->map(fn($r) => [
                'id' => $r->id,
                'from' => $r->reserved_at->toDateTimeString(),
                'to' => $r->reserved_until->toDateTimeString(),
                'type' => class_basename($r),
                'status' => $r->status->value,
                'reserver' => $r->reserver?->name ?? 'Unknown',
            ])->toArray(),
        ];
    }

    /**
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
                $reservation = $this->create(new CreateReservationData([
                    'reserver' => $user,
                    'startTime' => $recurringStart,
                    'endTime' => $recurringEnd,
                    'isRecurring' => true,
                    'recurrencePattern' => $recurrencePattern,
                ]));

                $reservations[] = $reservation;
            } catch (\InvalidArgumentException $e) {
                // Skip this slot if there's a conflict, but continue with others
                continue;
            }
        }

        return [
            'reservations' => $reservations,
        ];
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
        $conflicts ??= $this->getConflictsForDate($date);

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
            ->inRange($startTime, $endTime)
            ->when($excludeReservationId, function ($q) use ($excludeReservationId) {
                $q->where('id', '!=', $excludeReservationId);
            });

        return $query->get();
    }

    /**
     * Get all conflicts for a date.
     * @deprecated Use getConflicts() with date range instead
     */
    public function getConflictsForDate(Carbon $date, ?int $excludeReservationId = null): ConflictData
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();

        return $this->getConflicts($dayStart, $dayEnd, [
            'excludeId' => $excludeReservationId,
            'returnData' => true,
        ]);
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
    public function getValidEndTimes(string $startTime): array
    {
        $slots = [];
        $start = Carbon::createFromFormat('H:i', $startTime);

        // Minimum 1 hour, maximum 8 hours
        $earliestEnd = $start->copy()->addHour();
        $latestEnd = $start->copy()->addHours(8); // MAX_RESERVATION_DURATION

        // Don't go past 10 PM
        $businessEnd = Carbon::createFromTime(22, 0);
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
        $conflicts ??= $this->getConflictsForDate($date);

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

    /**
     * Unified validation method using Laravel's Validator.
     *
     * @param Carbon $startTime
     * @param Carbon $endTime
     * @param array $options Options:
     *   - user: User making the reservation (for user-specific rules)
     *   - excludeId: Reservation ID to exclude from conflict check
     *   - checkConflicts: Whether to check for reservation conflicts (default: true)
     *   - checkClosures: Whether to check for closure conflicts (default: true)
     *   - checkBusinessHours: Whether to validate business hours (default: true)
     *   - throwOnFailure: Whether to throw exception on validation failure (default: false)
     * @return \CorvMC\SpaceManagement\Data\ValidationResult
     * @throws \InvalidArgumentException When validation fails and throwOnFailure is true
     */
    public function validate(Carbon $startTime, Carbon $endTime, array $options = []): \CorvMC\SpaceManagement\Data\ValidationResult
    {
        $throwOnFailure = $options['throwOnFailure'] ?? false;

        // Prepare data for validation
        $data = [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'hours' => $startTime->diffInMinutes($endTime) / 60,
            'time_slot' => [
                'start_time' => $startTime,
                'end_time' => $endTime,
            ],
        ];

        // Create validator with rules
        $validator = Validator::make(
            $data,
            $this->getValidationRules($options),
            $this->getValidationMessages()
        );

        // Extract conflicts if validation fails
        if ($validator->fails()) {
            $conflicts = [];

            // Get conflicts from the custom rules if they were used
            foreach ($validator->getRules()['time_slot'] ?? [] as $rule) {
                if ($rule instanceof NoReservationOverlap) {
                    $conflicts['reservations'] = $rule->getConflicts();
                } elseif ($rule instanceof NoClosureOverlap) {
                    $conflicts['closures'] = $rule->getClosures();
                }
            }

            $result = \CorvMC\SpaceManagement\Data\ValidationResult::failure(
                $validator->errors()->all(),
                empty($conflicts) ? null : $conflicts
            );

            if ($throwOnFailure) {
                throw new \InvalidArgumentException(
                    'Validation failed: ' . implode('; ', $result->errors)
                );
            }

            return $result;
        }

        return \CorvMC\SpaceManagement\Data\ValidationResult::success();
    }

    /**
     * Legacy validation method.
     * @deprecated Use validate() instead
     */
    public function validateReservation(User $user, Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): array
    {
        $result = $this->validate($startTime, $endTime, [
            'user' => $user,
            'excludeId' => $excludeReservationId,
        ]);

        // Return errors in legacy format
        return $result->errors;
    }

    /**
     * Validate that a time slot is valid and available.
     * @deprecated Use validate() instead
     */
    public function validateTimeSlot(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): array
    {
        $result = $this->validate($startTime, $endTime, [
            'excludeId' => $excludeReservationId,
            'checkConflicts' => true,
        ]);

        // Return legacy array format
        return [
            'valid' => $result->valid,
            'errors' => $result->errors,
            'conflicts' => $result->conflicts,
        ];
    }

    /**
     * Internal helper to get conflicting closures.
     * Note: Buffer should already be applied by caller.
     */
    private function getConflictingClosuresInternal(Carbon $startTime, Carbon $endTime): Collection
    {

        $dayStart = $startTime->copy()->startOfDay();
        $dayEnd = $startTime->copy()->endOfDay();

        // Skip cache in testing to ensure fresh data
        if (app()->environment('testing')) {
            $dayClosures = SpaceClosure::query()
                ->with('createdBy')
                ->where('ends_at', '>', $dayStart)
                ->where('starts_at', '<', $dayEnd)
                ->get();
        } else {
            $cacheKey = 'closures.conflicts.' . $startTime->format('Y-m-d');

            // Cache all day's closures, then filter for specific conflicts
            $dayClosures = Cache::remember($cacheKey, 1800, function () use ($dayStart, $dayEnd) {
                return SpaceClosure::query()
                    ->with('createdBy')
                    ->where('ends_at', '>', $dayStart)
                    ->where('starts_at', '<', $dayEnd)
                    ->get();
            });
        }

        // Filter cached results for the specific time range
        $filteredClosures = $dayClosures->filter(function (SpaceClosure $closure) use ($startTime, $endTime) {
            return $closure->ends_at > $startTime && $closure->starts_at < $endTime;
        });

        // If invalid time period, return all potentially overlapping closures
        if ($endTime <= $startTime) {
            return $filteredClosures;
        }

        // Use Period for precise overlap detection
        $requestedPeriod = Period::make($startTime, $endTime, Precision::MINUTE());

        return $filteredClosures->filter(function (SpaceClosure $closure) use ($requestedPeriod) {
            return $closure->overlapsWithPeriod($requestedPeriod);
        });
    }

    /**
     * Check if a time slot is available.
     * @deprecated Use getConflicts() and check if empty
     */
    public function checkTimeSlotAvailability(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): bool
    {
        // Invalid time period means slot is not available
        if ($endTime <= $startTime) {
            return false;
        }

        $conflicts = $this->getConflicts($startTime, $endTime, [
            'excludeId' => $excludeReservationId,
            'includeClosures' => true,
        ]);

        return $conflicts['reservations']->isEmpty()
            && $conflicts['closures']->isEmpty();
    }


    /**
     * Get all conflicts for a time slot.
     * @deprecated Use getConflicts() instead
     */
    public function getAllConflicts(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): array
    {
        return $this->getConflicts($startTime, $endTime, [
            'excludeId' => $excludeReservationId,
            'includeClosures' => true,
        ]);
    }

    /**
     * Log activity for reservation operations.
     */
    protected function logActivity(string $event, Reservation $reservation, ?User $user, array $properties = []): void
    {
        activity('reservation')
            ->performedOn($reservation)
            ->causedBy($user)
            ->event($event)
            ->withProperties($properties)
            ->log(match ($event) {
                'created' => "Reservation created for {$reservation->reserved_at->format('M j, g:i A')}",
                'updated' => 'Reservation updated',
                'confirmed' => 'Reservation confirmed',
                'cancelled' => 'Reservation cancelled',
                default => $event,
            });
    }
}
