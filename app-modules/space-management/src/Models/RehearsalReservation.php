<?php

namespace CorvMC\SpaceManagement\Models;

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Finance\Concerns\HasCharges;
use CorvMC\Finance\Contracts\Chargeable;
use CorvMC\SpaceManagement\Actions\Reservations\CreateReservation;
use CorvMC\SpaceManagement\Database\Factories\RehearsalReservationFactory;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\Support\Contracts\Recurrable;
use CorvMC\Support\Models\RecurringSeries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Represents a practice space reservation made by an individual user.
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property \CorvMC\SpaceManagement\Enums\ReservationStatus $status
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
    use HasCharges, HasFactory;

    public function isOwnedBy(User $user): bool
    {
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
        return CreateReservation::run(
            $series->user,
            $startDateTime,
            $endDateTime,
            [
                'recurring_series_id' => $series->id,
                'instance_date' => $date->toDateString(),
                'is_recurring' => true,
                'recurrence_pattern' => ['source' => 'recurring_series'],
                'status' => ReservationStatus::Reserved,
            ]
        );
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
