<?php

namespace CorvMC\SpaceManagement\Models;

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Finance\Concerns\HasCharges;
use CorvMC\Finance\Contracts\Chargeable;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\States\ReservationState;
use CorvMC\Support\Contracts\Recurrable;
use CorvMC\Support\Models\RecurringSeries;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\ModelStates\HasStates;

/**
 * Represents a practice space reservation made by an individual user.
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property \CorvMC\SpaceManagement\States\ReservationState $status
 * @property numeric $hours_used
 * @property numeric $free_hours_used
 * @property bool $is_recurring
 * @property array<array-key, mixed>|null $recurrence_pattern
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $reserved_at
 * @property \Illuminate\Support\Carbon|null $reserved_until
 * @property int|null $recurring_series_id
 * @property \Illuminate\Support\Carbon|null $instance_date
 * @property string|null $cancellation_reason
 * @property string $type
 * @property string|null $reservable_type
 * @property int|null $reservable_id
 * @property string|null $google_calendar_event_id
 * @property-read \CorvMC\Finance\Models\Charge|null $charge
 *
 * @mixin \Eloquent
 */
class RehearsalReservation extends Reservation implements Chargeable, Recurrable
{
    use HasCharges, HasFactory, HasStates;

    /**
     * Validation rules specific to rehearsal reservations.
     */
    protected array $rules = [
        'reserved_at' => 'required|date|after:now',
        'reserved_until' => 'required|date|after:reserved_at',
        'reservable_type' => 'required|string',
        'reservable_id' => 'required|integer',
        'hours_used' => 'required|numeric|min:0.5|max:8',
    ];

    /**
     * Boot method for model events.
     */
    protected static function boot()
    {
        parent::boot();

        // Use static observer for validating event
        static::observe(new class {
            public function validating($reservation)
            {
                // Create virtual time_slot for custom rules
                $reservation->setAttribute('time_slot', [
                    'start_time' => $reservation->reserved_at,
                    'end_time' => $reservation->reserved_until,
                ]);

                // Add custom rules dynamically based on state
                $excludeId = $reservation->exists ? $reservation->id : null;
                $rules = $reservation->getRules();
                $rules['time_slot'] = [
                    new \CorvMC\SpaceManagement\Rules\NoReservationOverlap($excludeId),
                    new \CorvMC\SpaceManagement\Rules\NoClosureOverlap(),
                    new \CorvMC\SpaceManagement\Rules\WithinBusinessHours(9, 22),
                ];
                $reservation->setRules($rules);
            }

            public function validated($reservation)
            {
                // Remove virtual attribute after validation
                unset($reservation->time_slot);
            }
        });

        // Determine status on creation if not set
        static::creating(function ($reservation) {
            if (!$reservation->status) {
                $user = $reservation->getResponsibleUser();
                if ($user) {
                    $reservation->status = static::determineInitialStatus($user);
                }
            }
        });

        // Fire event for charge creation after validation passes
        static::created(function ($reservation) {
            event(new \CorvMC\SpaceManagement\Events\ReservationCreated(
                $reservation,
                deferCredits: $reservation->status === ReservationStatus::Reserved
            ));
        });
    }

    /**
     * Determine the initial status for a new reservation based on the user's role.
     */
    public static function determineInitialStatus(User $user): ReservationStatus
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
     * Determine the initial status for a reservation based on date and business rules.
     * Used by forms before a reservation is created.
     */
    public static function determineStatusForDate(Carbon $reservationDate, bool $isRecurring = false): string
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
     * Check if a reservation date needs a confirmation reminder.
     */
    public static function dateNeedsConfirmationReminder(Carbon $reservationDate, bool $isRecurring = false): bool
    {
        return ! $isRecurring && $reservationDate->isAfter(Carbon::now()->addWeek());
    }

    /**
     * Calculate when to send the confirmation reminder (1 week before).
     */
    public static function getConfirmationReminderDateForReservation(Carbon $reservationDate): Carbon
    {
        return $reservationDate->copy()->subWeek();
    }


    protected function registerStates(): void
    {
        $this->addState('status', ReservationState::class);
    }

    public function isOwnedBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->getResponsibleUser()?->is($user) ?? false;
    }

    /**
     * Check if this reservation requires payment.
     *
     * Delegates to the charge system via HasCharges trait.
     */
    public function requiresPayment(): bool
    {
        return $this->status->isActive() && $this->needsPayment();
    }

    // STI Abstract Method Implementations
    public function getReservationTypeLabel(): string
    {
        return 'Practice Space';
    }

    public function getIcon(): string
    {
        return 'tabler-metronome';
    }

    public function getDisplayTitle(): string
    {
        return $this->reservable?->name ?? 'Unknown User';
    }

    /**
     * Get the billable hours for this reservation.
     *
     * Implements Chargeable::getBillableUnits()
     */
    public function getBillableUnits(): float
    {
        if (! $this->reserved_at || ! $this->reserved_until) {
            return 0;
        }

        return $this->reserved_at->diffInMinutes($this->reserved_until) / 60;
    }

    /**
     * Get a human-readable description for the charge.
     *
     * Implements Chargeable::getChargeableDescription()
     */
    public function getChargeableDescription(): string
    {
        $hours = $this->getBillableUnits();
        $date = $this->reserved_at?->format('M j, Y') ?? 'TBD';

        return "Practice Space - {$hours} hour(s) on {$date}";
    }

    /**
     * Get the user responsible for payment.
     *
     * Implements Chargeable::getBillableUser()
     */
    public function getBillableUser(): User
    {
        // For rehearsal reservations, the reservable is always the User
        if ($this->reservable instanceof User) {
            return $this->reservable;
        }

        // Fallback to the user relationship
        return $this->user ?? throw new \RuntimeException('No billable user found for reservation');
    }

    // =========================================================================
    // Recurrable Interface Implementation
    // =========================================================================

    /**
     * Create a reservation instance from a recurring series.
     *
     * @throws \InvalidArgumentException If the reservation cannot be created (e.g., conflict)
     */
    public static function createFromRecurringSeries(RecurringSeries $series, Carbon $date): static
    {
        $startDateTime = $date->copy()->setTimeFromTimeString($series->start_time->format('H:i:s'));
        $endDateTime = $date->copy()->setTimeFromTimeString($series->end_time->format('H:i:s'));

        /** @var static */
        return static::create([
            'reservable_type' => User::class,
            'reservable_id' => $series->user_id,
            'reserved_at' => $startDateTime,
            'reserved_until' => $endDateTime,
            'recurring_series_id' => $series->id,
            'instance_date' => $date->toDateString(),
            'is_recurring' => true,
            'recurrence_pattern' => ['source' => 'recurring_series'],
            'status' => ReservationStatus::Reserved,
            'hours_used' => $startDateTime->diffInMinutes($endDateTime) / 60,
        ]);
    }

    public function canConfirm(): bool
    {
        // Only RehearsalReservations can be confirmed
        if (!$this instanceof RehearsalReservation) {
            return false;
        }

        // Delegate to state
        if (!$this->status->canConfirm()) {
            return false;
        }

        // Business rule: Can't confirm more than 5 days in advance
        $daysUntilReservation = now()->diffInDays($this->reserved_at, false);
        if ($daysUntilReservation > 5) {
            return false;
        }

        return true;
    }

    /**
     * Create a cancelled placeholder to track a skipped instance.
     */
    public static function createCancelledPlaceholder(RecurringSeries $series, Carbon $date): void
    {
        $startDateTime = $date->copy()->setTimeFromTimeString($series->start_time->format('H:i:s'));
        $endDateTime = $date->copy()->setTimeFromTimeString($series->end_time->format('H:i:s'));

        Reservation::create([
            'user_id' => $series->user_id,
            'type' => (new static)->getMorphClass(),
            'recurring_series_id' => $series->id,
            'instance_date' => $date->toDateString(),
            'reserved_at' => $startDateTime,
            'reserved_until' => $endDateTime,
            'status' => ReservationStatus::Cancelled,
            'cancellation_reason' => 'Scheduling conflict',
            'is_recurring' => true,
            'cost' => 0,
        ]);
    }

    /**
     * Check if an instance already exists for this date in the series.
     */
    public static function instanceExistsForDate(RecurringSeries $series, Carbon $date): bool
    {
        return Reservation::where('recurring_series_id', $series->id)
            ->whereDate('instance_date', $date->toDateString())
            ->exists();
    }

    /**
     * Cancel all future instances for a series.
     */
    public static function cancelFutureInstances(RecurringSeries $series, ?string $reason = null): int
    {
        $futureInstances = Reservation::where('recurring_series_id', $series->id)
            ->where('reserved_at', '>', now())
            ->whereIn('status', [
                ReservationStatus::Scheduled->value,
                ReservationStatus::Reserved->value,
                ReservationStatus::Confirmed->value,
            ])
            ->get();

        foreach ($futureInstances as $reservation) {
            $reservation->update([
                'status' => ReservationStatus::Cancelled,
                'cancellation_reason' => $reason ?? 'Recurring series cancelled',
            ]);
        }

        return $futureInstances->count();
    }
}
