<?php

namespace CorvMC\SpaceManagement\Models;

use App\Models\User;
use CorvMC\Finance\Concerns\HasCharges;
use CorvMC\Finance\Contracts\Chargeable;
use CorvMC\SpaceManagement\Database\Factories\RehearsalReservationFactory;
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
 * @property string $payment_status
 * @property string|null $payment_method
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property string|null $payment_notes
 * @property numeric $hours_used
 * @property numeric $free_hours_used
 * @property bool $is_recurring
 * @property array<array-key, mixed>|null $recurrence_pattern
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $reserved_at
 * @property \Illuminate\Support\Carbon|null $reserved_until
 * @property \Brick\Money\Money $cost
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
class RehearsalReservation extends Reservation implements Chargeable
{
    use HasCharges, HasFactory;

    protected $attributes = [
        'payment_status' => 'unpaid',
    ];

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
}
