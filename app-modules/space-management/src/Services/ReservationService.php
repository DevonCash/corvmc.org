<?php

namespace CorvMC\SpaceManagement\Services;

use App\Models\EventReservation;
use App\Models\User;
use App\Settings\ReservationSettings;
use Brick\Money\Money;
use Carbon\Carbon;
use CorvMC\Finance\Actions\MemberBenefits\GetUserMonthlyFreeHours;
use CorvMC\SpaceManagement\Services\ConflictData;
use CorvMC\Finance\Enums\ChargeStatus;
use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Models\CreditTransaction;
use CorvMC\SpaceManagement\Data\CreateReservationData;
use CorvMC\SpaceManagement\Data\ReservationUsageData;
use CorvMC\SpaceManagement\Data\UpdateReservationData;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Events\ReservationCancelled;
use CorvMC\SpaceManagement\Events\ReservationConfirmed;
use CorvMC\SpaceManagement\Events\ReservationCreated;
use CorvMC\SpaceManagement\Events\ReservationUpdated;
use CorvMC\SpaceManagement\Exceptions\ReservationConflictException;
use CorvMC\SpaceManagement\Models\Production;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\Models\SpaceClosure;
use CorvMC\SpaceManagement\Notifications\ReservationAutoCancelledNotification;
use CorvMC\SpaceManagement\Notifications\ReservationConfirmedNotification;
use CorvMC\SpaceManagement\Notifications\ReservationCreatedNotification;
use CorvMC\SpaceManagement\Notifications\ReservationCreatedTodayNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
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
     */
    public function create(CreateReservationData $data): RehearsalReservation
    {
        // Check for conflicts unless explicitly skipped
        if (!$data->skipConflictCheck) {
            $conflicts = $this->checkForConflicts($data->startTime, $data->endTime);
            if ($conflicts->isNotEmpty()) {
                throw new ReservationConflictException(
                    'Time slot conflicts with existing reservations',
                    $conflicts
                );
            }
        }

        return DB::transaction(function () use ($data) {
            $user = $data->getResponsibleUser();
            
            // Determine initial status based on user type
            $status = $this->determineInitialStatus($user);
            
            // Calculate duration
            $hoursUsed = $data->getDurationInHours();

            // Create the reservation
            $reservation = RehearsalReservation::create([
                'reserver_type' => get_class($data->reserver),
                'reserver_id' => $data->reserver->id,
                'reserved_at' => $data->startTime,
                'reserved_until' => $data->endTime,
                'hours_used' => $hoursUsed,
                'notes' => $data->notes,
                'status' => $status,
                'is_recurring' => $data->isRecurring,
                'recurring_series_id' => $data->recurringSeriesId,
            ]);

            // Fire event for charge creation
            // The Finance module listens to this event and creates the charge
            event(new ReservationCreated($reservation, deferCredits: $status === ReservationStatus::Reserved));

            $this->logActivity('created', $reservation, $user, [
                'hours' => $hoursUsed,
                'status' => $status->value,
            ]);

            return $reservation;
        });
    }

    /**
     * Update an existing reservation.
     */
    public function update(Reservation $reservation, UpdateReservationData $data): Reservation
    {
        return DB::transaction(function () use ($reservation, $data) {
            $updates = [];

            // Check for time changes and conflicts
            if (!$data->startTime instanceof \Spatie\LaravelData\Optional) {
                $updates['reserved_at'] = $data->startTime;
            }
            
            if (!$data->endTime instanceof \Spatie\LaravelData\Optional) {
                $updates['reserved_until'] = $data->endTime;
            }

            // If times are changing, check for conflicts
            if (isset($updates['reserved_at']) || isset($updates['reserved_until'])) {
                $startTime = $updates['reserved_at'] ?? $reservation->reserved_at;
                $endTime = $updates['reserved_until'] ?? $reservation->reserved_until;

                if (!$data->skipConflictCheck) {
                    $conflicts = $this->checkForConflicts($startTime, $endTime, $reservation);
                    if ($conflicts->isNotEmpty()) {
                        throw new ReservationConflictException(
                            'Updated time slot conflicts with existing reservations',
                            $conflicts
                        );
                    }
                }

                // Recalculate duration
                $updates['hours_used'] = $startTime->diffInMinutes($endTime) / 60;
            }

            if (!$data->notes instanceof \Spatie\LaravelData\Optional) {
                $updates['notes'] = $data->notes;
            }

            if (!$data->status instanceof \Spatie\LaravelData\Optional) {
                $updates['status'] = $data->status;
            }

            $reservation->update($updates);

            $this->logActivity('updated', $reservation, auth()->user(), $updates);

            return $reservation->fresh();
        });
    }

    /**
     * Confirm a reservation.
     * 
     * This changes the status to Confirmed. Payment handling is done
     * separately by the Finance module's PaymentService.
     * 
     * Authorization should be checked via policy before calling this method.
     */
    public function confirm(RehearsalReservation $reservation, bool $notifyUser = true): RehearsalReservation
    {
        // Business rule: Can't confirm if already confirmed or cancelled
        if (!in_array($reservation->status, [ReservationStatus::Scheduled, ReservationStatus::Reserved])) {
            throw new \Exception('Reservation cannot be confirmed in current status: ' . $reservation->status->value);
        }

        $user = $reservation->getResponsibleUser();
        $previousStatus = $reservation->status;

        DB::transaction(function () use ($reservation, $user, $previousStatus, $notifyUser) {
            $reservation->update([
                'status' => ReservationStatus::Confirmed,
                'confirmed_at' => now(),
            ]);

            // Fire event - Finance module listens for credit deduction if needed
            if ($previousStatus === ReservationStatus::Reserved) {
                event(new ReservationConfirmed($reservation, $previousStatus));
            }

            $this->logActivity('confirmed', $reservation, $user, [
                'previous_status' => $previousStatus->value,
            ]);

            if ($notifyUser) {
                try {
                    $user->notify(new ReservationConfirmedNotification($reservation));
                } catch (\Exception $e) {
                    Log::error('Failed to send reservation confirmation notification', [
                        'reservation_id' => $reservation->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        return $reservation->fresh();
    }

    /**
     * Cancel a reservation.
     * 
     * Authorization should be checked via policy before calling this method.
     */
    public function cancel(Reservation $reservation, ?string $reason = null): Reservation
    {
        // Business rule: Can't cancel if already cancelled
        if ($reservation->status === ReservationStatus::Cancelled) {
            throw new \Exception('Reservation is already cancelled');
        }

        return DB::transaction(function () use ($reservation, $reason) {
            $previousStatus = $reservation->status;
            
            $reservation->update([
                'status' => ReservationStatus::Cancelled,
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
            ]);

            // Fire event - Finance module handles refunds/credit restoration
            event(new ReservationCancelled($reservation, $previousStatus));

            $this->logActivity('cancelled', $reservation, auth()->user(), [
                'reason' => $reason,
                'previous_status' => $previousStatus->value,
            ]);

            return $reservation->fresh();
        });
    }

    /**
     * Get reservations for a user.
     */
    public function getUserReservations(User $user, ?string $status = null): Collection
    {
        $query = Reservation::forUser($user)
            ->with(['reserver', 'charge'])
            ->orderBy('reserved_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    /**
     * Get upcoming reservations.
     */
    public function getUpcomingReservations(int $days = 7): Collection
    {
        return Reservation::query()
            ->with(['reserver', 'charge'])
            ->where('reserved_at', '>=', now())
            ->where('reserved_at', '<=', now()->addDays($days))
            ->whereNotIn('status', [ReservationStatus::Cancelled])
            ->orderBy('reserved_at')
            ->get();
    }

    /**
     * Check for conflicting reservations.
     */
    public function checkForConflicts(
        Carbon $startTime,
        Carbon $endTime,
        ?Reservation $excludeReservation = null
    ): Collection {
        $query = Reservation::query()
            ->whereNotIn('status', [ReservationStatus::Cancelled])
            ->where(function ($q) use ($startTime, $endTime) {
                $q->whereBetween('reserved_at', [$startTime, $endTime])
                  ->orWhereBetween('reserved_until', [$startTime, $endTime])
                  ->orWhere(function ($q2) use ($startTime, $endTime) {
                      $q2->where('reserved_at', '<=', $startTime)
                         ->where('reserved_until', '>=', $endTime);
                  });
            });

        if ($excludeReservation) {
            $query->where('id', '!=', $excludeReservation->id);
        }

        $reservations = $query->get();

        // Also check for production conflicts
        $productions = Production::query()
            ->where(function ($q) use ($startTime, $endTime) {
                $q->whereBetween('starts_at', [$startTime, $endTime])
                  ->orWhereBetween('ends_at', [$startTime, $endTime])
                  ->orWhere(function ($q2) use ($startTime, $endTime) {
                      $q2->where('starts_at', '<=', $startTime)
                         ->where('ends_at', '>=', $endTime);
                  });
            })
            ->get();

        return $reservations->concat($productions);
    }

    /**
     * Get availability calendar for a date range.
     */
    public function getAvailabilityCalendar(Carbon $from, Carbon $to): array
    {
        $reservations = Reservation::query()
            ->whereNotIn('status', [ReservationStatus::Cancelled])
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('reserved_at', [$from, $to])
                      ->orWhereBetween('reserved_until', [$from, $to])
                      ->orWhere(function ($q) use ($from, $to) {
                          $q->where('reserved_at', '<=', $from)
                            ->where('reserved_until', '>=', $to);
                      });
            })
            ->get();

        $productions = Production::query()
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('starts_at', [$from, $to])
                      ->orWhereBetween('ends_at', [$from, $to])
                      ->orWhere(function ($q) use ($from, $to) {
                          $q->where('starts_at', '<=', $from)
                            ->where('ends_at', '>=', $to);
                      });
            })
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
            'productions' => $productions->map(fn($p) => [
                'id' => $p->id,
                'from' => $p->starts_at->toDateTimeString(),
                'to' => $p->ends_at->toDateTimeString(),
                'title' => $p->title,
            ])->toArray(),
        ];
    }

    /**
     * Check if a reservation is ready for confirmation.
     * This is a business rule check, not an authorization check.
     * 
     * @return array{can_confirm: bool, reason?: string}
     */
    public function checkConfirmationReadiness(Reservation $reservation): array
    {
        // Only RehearsalReservations can be confirmed
        if (!$reservation instanceof RehearsalReservation) {
            return [
                'can_confirm' => false,
                'reason' => 'Only rehearsal reservations can be confirmed',
            ];
        }

        // Must be in a confirmable status
        if (!in_array($reservation->status, [ReservationStatus::Scheduled, ReservationStatus::Reserved])) {
            return [
                'can_confirm' => false,
                'reason' => 'Reservation is already ' . $reservation->status->value,
            ];
        }

        // Business rule: Can't confirm more than 5 days in advance
        $daysUntilReservation = now()->diffInDays($reservation->reserved_at, false);
        if ($daysUntilReservation > 5) {
            return [
                'can_confirm' => false,
                'reason' => "Cannot confirm more than 5 days in advance. Please wait " . ($daysUntilReservation - 5) . " more days.",
            ];
        }

        return ['can_confirm' => true];
    }

    /**
     * Determine initial reservation status based on user.
     */
    protected function determineInitialStatus(User $user): ReservationStatus
    {
        if ($user->hasRole(['admin', 'staff', 'practice space manager'])) {
            return ReservationStatus::Confirmed;
        }

        if ($user->hasRole('sustaining member')) {
            return ReservationStatus::Scheduled;
        }

        return ReservationStatus::Reserved;
    }

    /**
     * Calculate the cost breakdown for a reservation.
     */
    public function calculateReservationCost(User $user, Carbon $startTime, Carbon $endTime): array
    {
        $hours = $startTime->diffInMinutes($endTime) / 60;

        // Use fresh calculation (bypass cache) for transaction safety during reservation creation
        $remainingFreeHours = $user->getRemainingFreeHours();

        $freeHours = $user->isSustainingMember() ? min($hours, $remainingFreeHours) : 0;
        $paidHours = max(0, $hours - $freeHours);

        $cost = Money::of(15.00, 'USD')->multipliedBy($paidHours);

        return [
            'total_hours' => $hours,
            'free_hours' => $freeHours,
            'paid_hours' => $paidHours,
            'cost' => $cost,
            'hourly_rate' => 15.00,
            'is_sustaining_member' => $user->isSustainingMember(),
            'remaining_free_hours' => $remainingFreeHours,
        ];
    }

    /**
     * Create a Stripe checkout session for a reservation payment.
     */
    public function createCheckoutSession(RehearsalReservation $reservation)
    {
        $user = $reservation->reservable;

        // Ensure user has a Stripe customer ID
        if (! $user->hasStripeId()) {
            $user->createAsStripeCustomer();
        }

        $priceId = config('services.stripe.practice_space_price_id');

        if (! $priceId) {
            throw new \Exception('Practice space price not configured. Run: php artisan practice-space:create-price');
        }

        // Calculate paid hours and convert to 30-minute blocks
        $paidHours = $reservation->hours_used - $reservation->free_hours_used;
        $paidBlocks = Reservation::hoursToBlocks($paidHours);

        if ($paidBlocks <= 0) {
            throw new \Exception('No payment required for this reservation.');
        }

        // Use Cashier's checkout method
        $checkout = $user->checkout([
            $priceId => $paidBlocks,
        ], [
            'success_url' => route('checkout.success').'?session_id={CHECKOUT_SESSION_ID}&user_id='.$reservation->getResponsibleUser()->id,
            'cancel_url' => route('checkout.cancel').'?user_id='.$reservation->getResponsibleUser()->id.'&type=practice_space_reservation',
            'metadata' => [
                'reservation_id' => $reservation->id,
                'user_id' => $user->id,
                'type' => 'practice_space_reservation',
                'free_hours_used' => $reservation->free_hours_used,
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'reservation_id' => $reservation->id,
                    'user_id' => $user->id,
                    'type' => 'practice_space_reservation',
                    'free_hours_used' => $reservation->free_hours_used,
                ],
            ],
        ]);

        return $checkout;
    }

    /**
     * Create recurring reservations for sustaining members.
     */
    public function createRecurringReservation(User $user, Carbon $startTime, Carbon $endTime, array $recurrencePattern): array
    {
        if (! $user->isSustainingMember()) {
            throw new \InvalidArgumentException('Only sustaining members can create recurring reservations.');
        }

        // Estimate credit availability across renewal cycles (informational only)
        $creditEstimate = $this->estimateRecurringCreditCost($user, $startTime, $endTime, $recurrencePattern);

        $reservations = [];
        $weeks = $recurrencePattern['weeks'] ?? 4; // Default to 4 weeks
        $interval = $recurrencePattern['interval'] ?? 1; // Every N weeks

        for ($i = 0; $i < $weeks; $i++) {
            $weekOffset = $i * $interval;
            $recurringStart = $startTime->copy()->addWeeks($weekOffset);
            $recurringEnd = $endTime->copy()->addWeeks($weekOffset);

            try {
                $reservation = $this->createReservationFromUser($user, $recurringStart, $recurringEnd, [
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

        return [
            'reservations' => $reservations,
            'credit_estimate' => $creditEstimate,
        ];
    }

    /**
     * Determine the initial status for a reservation based on business rules.
     */
    public function determineReservationStatus(Carbon $reservationDate, bool $isRecurring = false): string
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
        return ! $isRecurring && $reservationDate->isAfter(Carbon::now()->addWeek());
    }

    /**
     * Calculate when to send the confirmation reminder (1 week before).
     */
    public function getConfirmationReminderDate(Carbon $reservationDate): Carbon
    {
        return $reservationDate->copy()->subWeek();
    }

    /**
     * Estimate if user will have enough credits for recurring reservations.
     */
    public function estimateRecurringCreditCost(User $user, Carbon $startTime, Carbon $endTime, array $recurrencePattern): array
    {
        $weeks = $recurrencePattern['weeks'] ?? 4;
        $interval = $recurrencePattern['interval'] ?? 1;

        // Calculate blocks needed per instance
        $hours = $startTime->diffInMinutes($endTime) / 60;
        $blocksPerInstance = Reservation::hoursToBlocks($hours);
        $totalBlocksNeeded = $blocksPerInstance * $weeks;

        // Get current credit balance
        $currentBalance = $user->getCreditBalance(CreditType::FreeHours);

        // Get monthly allocation amount
        $monthlyFreeHours = GetUserMonthlyFreeHours::run($user);
        $monthlyBlockAllocation = Reservation::hoursToBlocks($monthlyFreeHours);

        // Find last allocation date to predict next allocations
        $lastAllocation = CreditTransaction::where('user_id', $user->id)
            ->where('credit_type', CreditType::FreeHours->value)
            ->where('source', 'monthly_reset')
            ->latest('created_at')
            ->first();

        // Simulate credit availability across the recurring period
        $simulatedBalance = $currentBalance;
        $estimatedAllocations = [];
        $lastAllocationDate = $lastAllocation ? $lastAllocation->created_at : now();

        // Calculate when reservations will be confirmed (3 days before each)
        $confirmationDates = [];
        for ($i = 0; $i < $weeks; $i++) {
            $weekOffset = $i * $interval;
            $recurringStart = $startTime->copy()->addWeeks($weekOffset);
            // Credits are deducted 3 days before reservation (confirmation deadline)
            $confirmationDate = $recurringStart->copy()->subDays(3);
            $confirmationDates[] = $confirmationDate;
        }

        // Sort confirmation dates
        sort($confirmationDates);

        // Simulate credit allocations and deductions
        $nextAllocationDate = $lastAllocationDate->copy()->addMonthNoOverflow();
        foreach ($confirmationDates as $confirmationDate) {
            // Check if we'll get a credit allocation before this confirmation
            while ($nextAllocationDate->lte($confirmationDate)) {
                $simulatedBalance += $monthlyBlockAllocation;
                $estimatedAllocations[] = [
                    'date' => $nextAllocationDate->toDateString(),
                    'amount' => $monthlyBlockAllocation,
                ];
                $nextAllocationDate = $nextAllocationDate->copy()->addMonthNoOverflow();
            }

            // Deduct blocks for this confirmation
            $simulatedBalance -= $blocksPerInstance;
        }

        $sufficient = $simulatedBalance >= 0;
        $shortfall = max(0, -$simulatedBalance);

        return [
            'sufficient' => $sufficient,
            'total_blocks_needed' => $totalBlocksNeeded,
            'blocks_per_instance' => $blocksPerInstance,
            'current_balance' => $currentBalance,
            'monthly_allocation' => $monthlyBlockAllocation,
            'estimated_allocations' => $estimatedAllocations,
            'final_balance' => $simulatedBalance,
            'shortfall' => $shortfall,
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

        $reservations = Reservation::where('status', '!=', 'cancelled')
            ->where('reserved_until', '>', $dayStart)
            ->where('reserved_at', '<', $dayEnd)
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
            ->filter(fn (Period $gap) => $gap->length() >= $minimumDurationMinutes)
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
            ->where('status', '!=', 'cancelled')
            ->where('reserved_until', '>', $dayStart)
            ->where('reserved_at', '<', $dayEnd)
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
     * Get potentially conflicting reservations for a time slot.
     */
    public function getConflictingReservations(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): Collection
    {
        $bufferMinutes = app(ReservationSettings::class)->buffer_minutes;
        $bufferedStart = $startTime->copy()->subMinutes($bufferMinutes);
        $bufferedEnd = $endTime->copy()->addMinutes($bufferMinutes);

        $dayStart = $startTime->copy()->startOfDay();
        $dayEnd = $startTime->copy()->endOfDay();

        // Skip cache in testing to ensure fresh data
        if (app()->environment('testing')) {
            $dayReservations = Reservation::with('reservable')
                ->where('status', '!=', ReservationStatus::Cancelled)
                ->where('reserved_until', '>', $dayStart)
                ->where('reserved_at', '<', $dayEnd)
                ->get();
        } else {
            $cacheKey = 'reservations.conflicts.'.$startTime->format('Y-m-d');

            // Cache all day's reservations, then filter for specific conflicts
            $dayReservations = Cache::remember($cacheKey, 1800, function () use ($dayStart, $dayEnd) {
                return Reservation::with('reservable')
                    ->where('status', '!=', ReservationStatus::Cancelled)
                    ->where('reserved_until', '>', $dayStart)
                    ->where('reserved_at', '<', $dayEnd)
                    ->get();
            });
        }

        // Filter cached results for the specific time range (with buffer) and exclusion
        $filteredReservations = $dayReservations->filter(function (Reservation $reservation) use ($bufferedStart, $bufferedEnd, $excludeReservationId) {
            if ($excludeReservationId && $reservation->id === $excludeReservationId) {
                return false;
            }

            return $reservation->reserved_until > $bufferedStart && $reservation->reserved_at < $bufferedEnd;
        });

        // If invalid time period, return all potentially overlapping reservations
        if ($bufferedEnd <= $bufferedStart) {
            return $filteredReservations;
        }

        // Use Period for precise overlap detection with buffered times
        $requestedPeriod = Period::make($bufferedStart, $bufferedEnd, Precision::MINUTE());

        return $filteredReservations->filter(function (Reservation $reservation) use ($requestedPeriod) {
            return $reservation->overlapsWith($requestedPeriod);
        });
    }

    /**
     * Get all reservations and productions for a date in a single batch.
     */
    public function getConflictsForDate(Carbon $date, ?int $excludeReservationId = null): ConflictData
    {
        $bufferMinutes = app(ReservationSettings::class)->buffer_minutes;

        $reservations = $this->getReservationsForDate($date, $excludeReservationId);
        $productions = $this->getProductionsForDate($date);

        return new ConflictData($reservations, $productions, $bufferMinutes);
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

        $allocatedFreeHours = GetUserMonthlyFreeHours::run($user);

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
     * Handle failed or cancelled payment.
     */
    public function handleFailedPayment(RehearsalReservation $reservation, ?string $sessionId = null): void
    {
        $notes = $sessionId ? "Payment failed/cancelled (Session: {$sessionId})" : 'Payment cancelled by user';

        // Log the failure on the charge if it exists
        if ($reservation->charge) {
            $reservation->charge->update([
                'notes' => $notes,
            ]);
        }
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

        // Require advance notice - no same-day reservations
        if ($startTime->isToday()) {
            $errors[] = 'Same-day reservations are not allowed. Please schedule for tomorrow or later.';
        }

        // Check if end time is after start time
        if ($endTime->lte($startTime)) {
            $errors[] = 'End time must be after start time.';
        }

        $hours = $startTime->diffInMinutes($endTime) / 60;

        // Check minimum duration
        if ($hours < 1) { // MIN_RESERVATION_DURATION
            $errors[] = 'Minimum reservation duration is 1 hour(s).';
        }

        // Check maximum duration
        if ($hours > 8) { // MAX_RESERVATION_DURATION
            $errors[] = 'Maximum reservation duration is 8 hours.';
        }

        // Check for conflicts
        if (! $this->checkTimeSlotAvailability($startTime, $endTime, $excludeReservationId)) {
            $allConflicts = $this->getAllConflicts($startTime, $endTime, $excludeReservationId);
            $conflictMessages = [];

            if ($allConflicts['reservations']->isNotEmpty()) {
                $reservationConflicts = $allConflicts['reservations']->map(function ($r) {
                    $userName = $r->user?->name ?? $r->reservable?->name ?? 'Unknown';

                    return $userName.' ('.$r->reserved_at->format('M j, g:i A').' - '.$r->reserved_until->format('g:i A').')';
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
     * Validate that a time slot is valid and available.
     */
    public function validateTimeSlot(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): array
    {
        if ($endTime <= $startTime) {
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
        if ($duration < 1) { // MIN_RESERVATION_DURATION
            return [
                'valid' => false,
                'errors' => ['Minimum reservation duration is 1 hour'],
            ];
        }

        if ($duration > 8) { // MAX_RESERVATION_DURATION
            return [
                'valid' => false,
                'errors' => ['Maximum reservation duration is 8 hours'],
            ];
        }

        // Check for conflicts using existing methods
        $conflicts = $this->getAllConflicts($startTime, $endTime, $excludeReservationId);
        $errors = [];

        if ($conflicts['reservations']->isNotEmpty()) {
            $errors[] = 'Conflicts with '.$conflicts['reservations']->count().' existing reservation(s)';
        }

        if ($conflicts['productions']->isNotEmpty()) {
            $errors[] = 'Conflicts with '.$conflicts['productions']->count().' production(s)';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Get space closures that conflict with a time slot.
     */
    public function getConflictingClosures(Carbon $startTime, Carbon $endTime): Collection
    {
        $bufferMinutes = app(ReservationSettings::class)->buffer_minutes;
        $bufferedStart = $startTime->copy()->subMinutes($bufferMinutes);
        $bufferedEnd = $endTime->copy()->addMinutes($bufferMinutes);

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
            $cacheKey = 'closures.conflicts.'.$startTime->format('Y-m-d');

            // Cache all day's closures, then filter for specific conflicts
            $dayClosures = Cache::remember($cacheKey, 1800, function () use ($dayStart, $dayEnd) {
                return SpaceClosure::query()
                    ->with('createdBy')
                    ->where('ends_at', '>', $dayStart)
                    ->where('starts_at', '<', $dayEnd)
                    ->get();
            });
        }

        // Filter cached results for the specific time range (with buffer)
        $filteredClosures = $dayClosures->filter(function (SpaceClosure $closure) use ($bufferedStart, $bufferedEnd) {
            return $closure->ends_at > $bufferedStart && $closure->starts_at < $bufferedEnd;
        });

        // If invalid time period, return all potentially overlapping closures
        if ($bufferedEnd <= $bufferedStart) {
            return $filteredClosures;
        }

        // Use Period for precise overlap detection with buffered times
        $requestedPeriod = Period::make($bufferedStart, $bufferedEnd, Precision::MINUTE());

        return $filteredClosures->filter(function (SpaceClosure $closure) use ($requestedPeriod) {
            return $closure->overlapsWithPeriod($requestedPeriod);
        });
    }

    /**
     * Check if a time slot is available (no conflicts with reservations, productions, or closures).
     */
    public function checkTimeSlotAvailability(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): bool
    {
        // Invalid time period means slot is not available
        if ($endTime <= $startTime) {
            return false;
        }

        $conflicts = $this->getAllConflicts($startTime, $endTime, $excludeReservationId);

        return $conflicts['reservations']->isEmpty()
            && $conflicts['productions']->isEmpty()
            && $conflicts['closures']->isEmpty();
    }

    /**
     * Get all active reservations that overlap with a closure period.
     */
    public function getReservationsAffectedByClosure(Carbon $startsAt, Carbon $endsAt): Collection
    {
        return Reservation::with(['reservable', 'user'])
            ->where('status', '!=', ReservationStatus::Cancelled)
            ->where('reserved_until', '>', $startsAt)
            ->where('reserved_at', '<', $endsAt)
            ->orderBy('reserved_at')
            ->get();
    }

    /**
     * Auto-cancel Reserved instances that are within 3 days and haven't been confirmed.
     */
    public function autoCancelUnconfirmedReservations(): Collection
    {
        $threeDaysFromNow = now()->addDays(3);

        // Find all Reserved instances that are within 3 days
        $reservationsToCancel = RehearsalReservation::where('status', ReservationStatus::Reserved)
            ->where('reserved_at', '<=', $threeDaysFromNow)
            ->where('reserved_at', '>', now())
            ->get();

        $cancelled = collect();

        foreach ($reservationsToCancel as $reservation) {
            try {
                $user = $reservation->getResponsibleUser();

                $reservation->update([
                    'status' => ReservationStatus::Cancelled,
                    'cancellation_reason' => 'Not confirmed within 3-day window',
                ]);

                activity('reservation')
                    ->performedOn($reservation)
                    ->event('auto_cancelled')
                    ->withProperties([
                        'original_status' => ReservationStatus::Reserved->value,
                    ])
                    ->log('Reservation auto-cancelled: not confirmed within 3-day window');

                // Send notification to user
                try {
                    $user->notify(new ReservationAutoCancelledNotification($reservation));
                } catch (\Exception $e) {
                    \Log::error('Failed to send auto-cancel notification', [
                        'reservation_id' => $reservation->id,
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $cancelled->push($reservation);

                \Log::info('Auto-cancelled unconfirmed reservation', [
                    'reservation_id' => $reservation->id,
                    'user_id' => $user->id,
                    'reserved_at' => $reservation->reserved_at,
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to auto-cancel reservation', [
                    'reservation_id' => $reservation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $cancelled;
    }

    /**
     * Get event reservations that conflict with a time slot.
     */
    public function getConflictingProductions(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): Collection
    {
        $bufferMinutes = app(ReservationSettings::class)->buffer_minutes;
        $bufferedStart = $startTime->copy()->subMinutes($bufferMinutes);
        $bufferedEnd = $endTime->copy()->addMinutes($bufferMinutes);

        $cacheKey = 'event-reservations.conflicts.'.$startTime->format('Y-m-d');

        // Cache all day's event reservations, then filter for specific conflicts
        $dayEventReservations = Cache::remember($cacheKey, 3600, function () use ($startTime) {
            $dayStart = $startTime->copy()->startOfDay();
            $dayEnd = $startTime->copy()->endOfDay();

            return EventReservation::query()
                ->where('status', '!=', 'cancelled')
                ->where('reserved_until', '>', $dayStart)
                ->where('reserved_at', '<', $dayEnd)
                ->with('event')
                ->get();
        });

        // Filter cached results for the specific time range (with buffer) and exclusion
        $filteredEventReservations = $dayEventReservations->filter(function (EventReservation $reservation) use ($bufferedStart, $bufferedEnd, $excludeReservationId) {
            if ($excludeReservationId && $reservation->id === $excludeReservationId) {
                return false;
            }

            return $reservation->reserved_until > $bufferedStart && $reservation->reserved_at < $bufferedEnd;
        });

        // If invalid time period, return all potentially overlapping event reservations
        if ($bufferedEnd <= $bufferedStart) {
            return $filteredEventReservations;
        }

        // Use Period for precise overlap detection with buffered times
        $requestedPeriod = Period::make($bufferedStart, $bufferedEnd, Precision::MINUTE());

        return $filteredEventReservations->filter(function (EventReservation $reservation) use ($requestedPeriod) {
            return $reservation->overlapsWith($requestedPeriod);
        });
    }

    /**
     * Get all conflicts (reservations, productions, and closures) for a time slot.
     */
    public function getAllConflicts(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): array
    {
        return [
            'reservations' => $this->getConflictingReservations($startTime, $endTime, $excludeReservationId),
            'productions' => $this->getConflictingProductions($startTime, $endTime, $excludeReservationId),
            'closures' => $this->getConflictingClosures($startTime, $endTime),
        ];
    }

    /**
     * Update an existing reservation (alternative method for direct use).
     */
    public function updateReservation(Reservation $reservation, Carbon $startTime, Carbon $endTime, array $options = []): Reservation
    {
        // Prevent updating paid/comped reservations unless explicitly allowed via payment_status update
        // Check the Charge model for payment status
        if ($reservation instanceof RehearsalReservation) {
            $chargeStatus = $reservation->charge?->status;
            if ($chargeStatus && $chargeStatus->isSettled()
                && $chargeStatus !== ChargeStatus::Paid
                && $chargeStatus !== ChargeStatus::CoveredByCredits) {
                throw new \InvalidArgumentException('Cannot update comped or refunded reservations. Please cancel and create a new reservation, or update via admin panel.');
            }
        }

        // Only validate for rehearsal reservations
        if ($reservation instanceof RehearsalReservation) {
            $errors = $this->validateReservation($reservation->getResponsibleUser(), $startTime, $endTime, $reservation->id);

            if (! empty($errors)) {
                throw new \InvalidArgumentException('Validation failed: '.implode(' ', $errors));
            }
        }

        // Capture old billable units before update for credit adjustment
        $oldBillableUnits = $reservation instanceof RehearsalReservation
            ? $reservation->getBillableUnits()
            : 0;

        return DB::transaction(function () use ($reservation, $startTime, $endTime, $options, $oldBillableUnits) {
            $hours = $startTime->diffInMinutes($endTime) / 60;

            $updateData = [
                'reserved_at' => $startTime,
                'reserved_until' => $endTime,
                'hours_used' => $hours,
                'notes' => $options['notes'] ?? $reservation->notes,
                'status' => $options['status'] ?? $reservation->status,
            ];

            $reservation->update($updateData);

            // Fire event for Finance module to handle credit adjustments
            if ($reservation instanceof RehearsalReservation) {
                ReservationUpdated::dispatch($reservation, $oldBillableUnits);
            }

            return $reservation;
        });
    }

    /**
     * Create a new reservation (alternative method for direct use).
     */
    public function createReservationFromUser(User $user, Carbon $startTime, Carbon $endTime, array $options = []): RehearsalReservation
    {
        // Validate the reservation
        $errors = $this->validateReservation($user, $startTime, $endTime);

        if (! empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: '.implode(' ', $errors));
        }

        $status = $options['status'] ?? ReservationStatus::Scheduled;

        // Auto-confirm near-term reservations (< 3 days) at creation time
        $daysUntilReservation = now()->diffInDays($startTime, false);
        $shouldAutoConfirm = $daysUntilReservation < 3
            && $status !== ReservationStatus::Reserved
            && $status !== ReservationStatus::Confirmed;

        if ($shouldAutoConfirm) {
            $status = ReservationStatus::Confirmed;
        }

        $reservation = DB::transaction(function () use ($user, $startTime, $endTime, $options, $status) {
            // Calculate hours for display/tracking (pricing handled by Finance)
            $hours = $startTime->diffInMinutes($endTime) / 60;

            // Create reservation (scheduling only - no pricing/credit logic)
            $reservation = RehearsalReservation::create([
                'user_id' => $user->id,
                'reservable_type' => Relation::getMorphAlias(User::class),
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

        // Send creation notification (handles both Scheduled and Confirmed messaging)
        try {
            $user->notify(new ReservationCreatedNotification($reservation));
        } catch (\Exception $e) {
            \Log::error('Failed to send reservation creation notification', [
                'reservation_id' => $reservation->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
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

        return $reservation;
    }

    /**
     * Process a successful reservation checkout.
     */
    public function processReservationCheckout(int $reservationId, string $sessionId, ?string $paymentIntentId = null): bool
    {
        try {
            if (! $reservationId) {
                Log::warning('No reservation ID provided for checkout processing', ['session_id' => $sessionId]);

                return false;
            }

            $reservation = Reservation::find($reservationId);

            if (! $reservation) {
                Log::error('Reservation not found for checkout', [
                    'reservation_id' => $reservationId,
                    'session_id' => $sessionId,
                ]);

                return false;
            }

            // Skip if already paid (idempotency check via Charge)
            if ($reservation->charge && !$reservation->charge->requiresPayment()) {
                Log::info('Reservation already paid, skipping', [
                    'reservation_id' => $reservationId,
                    'session_id' => $sessionId,
                ]);

                return true;
            }

            // Process the successful payment (this action is also idempotent)
            $this->handleSuccessfulPayment($reservation, $sessionId, $paymentIntentId);

            Log::info('Successfully processed reservation checkout', [
                'reservation_id' => $reservationId,
                'session_id' => $sessionId,
                'amount' => $reservation->cost,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error processing reservation checkout', [
                'error' => $e->getMessage(),
                'reservation_id' => $reservationId,
                'session_id' => $sessionId,
            ]);

            return false;
        }
    }

    /**
     * Handle successful payment and update reservation.
     */
    public function handleSuccessfulPayment(RehearsalReservation $reservation, string $sessionId, ?string $paymentIntentId = null): bool
    {
        // Idempotency check - skip if already paid
        if ($reservation->isPaid()) {
            return true;
        }

        $user = $reservation->getResponsibleUser();

        // Update Charge record
        $reservation->charge?->markAsPaid('stripe', $sessionId, $paymentIntentId, "Paid via Stripe checkout");

        activity('reservation')
            ->performedOn($reservation)
            ->causedBy($user)
            ->event('payment_recorded')
            ->withProperties([
                'payment_method' => 'stripe',
                'session_id' => $sessionId,
            ])
            ->log('Payment completed via Stripe checkout');

        // Confirm the reservation
        $reservation->update([
            'status' => ReservationStatus::Confirmed,
        ]);

        $reservation->refresh();

        // Send notification outside transaction - don't let email failures affect the payment
        try {
            $user->notify(new ReservationConfirmedNotification($reservation));
        } catch (\Exception $e) {
            \Log::error('Failed to send reservation confirmation email after payment', [
                'reservation_id' => $reservation->id,
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    /**
     * Get reservations for a date (helper method).
     */
    private function getReservationsForDate(Carbon $date, ?int $excludeReservationId): SupportCollection
    {
        $cacheKey = 'reservations.conflicts.'.$date->format('Y-m-d');

        $dayReservations = Cache::remember($cacheKey, 1800, function () use ($date) {
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            return Reservation::with('reservable')
                ->where('status', '!=', 'cancelled')
                ->where('reserved_until', '>', $dayStart)
                ->where('reserved_at', '<', $dayEnd)
                ->get();
        });

        if ($excludeReservationId) {
            return $dayReservations->reject(fn (Reservation $r) => $r->id === $excludeReservationId);
        }

        return $dayReservations;
    }

    /**
     * Get productions for a date (helper method).
     */
    private function getProductionsForDate(Carbon $date): SupportCollection
    {
        $cacheKey = 'event-reservations.conflicts.'.$date->format('Y-m-d');

        return Cache::remember($cacheKey, 3600, function () use ($date) {
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            return EventReservation::query()
                ->where('status', '!=', 'cancelled')
                ->where('reserved_until', '>', $dayStart)
                ->where('reserved_at', '<', $dayEnd)
                ->with('event')
                ->get();
        });
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
            ->log(match($event) {
                'created' => "Reservation created for {$reservation->reserved_at->format('M j, g:i A')}",
                'updated' => 'Reservation updated',
                'confirmed' => 'Reservation confirmed',
                'cancelled' => 'Reservation cancelled',
                default => $event,
            });
    }
}

