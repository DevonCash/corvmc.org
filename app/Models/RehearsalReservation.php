<?php

namespace App\Models;

use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Represents a practice space reservation made by an individual user.
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property \App\Enums\ReservationStatus $status
 * @property \CorvMC\Finance\Enums\PaymentStatus $payment_status
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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read string $cost_display
 * @property-read float $duration
 * @property-read array $payment_status_badge
 * @property-read string $status_display
 * @property-read string $time_range
 * @property-read \App\Models\RecurringSeries|null $recurringSeries
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $reservable
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $user
 *
 * @method static \Database\Factories\ReservationFactory factory($count = null, $state = [])
 * @method static Builder<static>|RehearsalReservation needsAttention()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereCancellationReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereFreeHoursUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereGoogleCalendarEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereHoursUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereInstanceDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereIsRecurring($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation wherePaidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation wherePaymentNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation wherePaymentStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereRecurrencePattern($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereRecurringSeriesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereReservableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereReservableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereReservedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereReservedUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class RehearsalReservation extends Reservation
{
    use HasFactory;

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
}
