<?php

namespace CorvMC\Equipment\Models;

use CorvMC\Support\Concerns\HasTimePeriod;
use App\Models\User;
use CorvMC\Equipment\States\EquipmentLoan\Cancelled;
use CorvMC\Equipment\States\EquipmentLoan\CheckedOut;
use CorvMC\Equipment\States\EquipmentLoan\DamageReported;
use CorvMC\Equipment\States\EquipmentLoan\DropoffScheduled;
use CorvMC\Equipment\States\EquipmentLoan\EquipmentLoanState;
use CorvMC\Equipment\States\EquipmentLoan\Overdue;
use CorvMC\Equipment\States\EquipmentLoan\ReadyForPickup;
use CorvMC\Equipment\States\EquipmentLoan\Requested;
use CorvMC\Equipment\States\EquipmentLoan\Returned;
use CorvMC\Equipment\States\EquipmentLoan\StaffPreparing;
use CorvMC\Equipment\States\EquipmentLoan\StaffProcessingReturn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\ModelStates\HasStates;
use Spatie\Period\Period;

/**
 * Represents a loan of equipment from CMC to a member.
 *
 * Tracks the checkout/return process, condition, and financial aspects
 * of equipment loans to members.
 *
 * @property int $id
 * @property int $equipment_id
 * @property int $borrower_id
 * @property \Illuminate\Support\Carbon|null $checked_out_at
 * @property \Illuminate\Support\Carbon $due_at
 * @property \Illuminate\Support\Carbon|null $returned_at
 * @property string|null $condition_in
 * @property numeric $security_deposit
 * @property numeric $rental_fee
 * @property string|null $notes
 * @property string|null $damage_notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property EquipmentLoanState $state
 * @property \Illuminate\Support\Carbon $reserved_from
 * @property string|null $condition_out
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read User $borrower
 * @property-read Equipment $equipment
 * @property-read int $days_out
 * @property-read int $days_overdue
 * @property-read bool $is_active
 * @property-read bool $is_overdue
 * @property-read bool $is_reservation_active
 * @property-read bool $is_reservation_expired
 * @property-read bool $is_reservation_upcoming
 * @property-read bool $is_returned
 * @property-read string $total_fees
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan byBorrower(User $borrower)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan cancelled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan checkedOut()
 * @method static \CorvMC\Equipment\Database\Factories\EquipmentLoanFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan forEquipment(Equipment $equipment)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan onDate($date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan orWhereNotState(string $column, $states)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan orWhereState(string $column, $states)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan overdue()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan overlappingPeriod(\Spatie\Period\Period $period)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan requiringMemberAction()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan requiringStaffAction()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan returned()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan upcomingReservations()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan whereBorrowerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan whereCheckedOutAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan whereConditionIn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan whereConditionOut($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan whereDamageNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan whereDueAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan whereEquipmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan whereNotState(string $column, $states)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan whereRentalFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan whereReservedFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan whereReturnedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan whereSecurityDeposit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentLoan withActiveReservations()
 *
 * @mixin \Eloquent
 */
class EquipmentLoan extends Model
{
    use HasFactory, HasStates, HasTimePeriod, LogsActivity;

    /**
     * Get the name of the start time field for this model.
     */
    protected function getStartTimeField(): string
    {
        return 'reserved_from';
    }

    /**
     * Get the name of the end time field for this model.
     */
    protected function getEndTimeField(): string
    {
        return 'due_at';
    }

    protected $fillable = [
        'equipment_id',
        'borrower_id',
        'reserved_from',
        'checked_out_at',
        'due_at',
        'returned_at',
        'condition_out',
        'condition_in',
        'security_deposit',
        'rental_fee',
        'notes',
        'damage_notes',
        'state',
    ];

    protected $casts = [
        'reserved_from' => 'datetime',
        'checked_out_at' => 'datetime',
        'due_at' => 'datetime',
        'returned_at' => 'datetime',
        'security_deposit' => 'decimal:2',
        'rental_fee' => 'decimal:2',
        'state' => EquipmentLoanState::class,
    ];

    protected $attributes = [
        'security_deposit' => '0.00',
        'rental_fee' => '0.00',
    ];

    /**
     * Get the equipment being loaned.
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * Get the user borrowing the equipment.
     */
    public function borrower(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }

    /**
     * Check if the given user is the borrower of this loan.
     */
    public function isBorrower(User $user): bool
    {
        return $this->borrower_id === $user->id;
    }

    /**
     * Check if loan is currently active (not yet returned/cancelled/lost).
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->state instanceof Requested ||
               $this->state instanceof StaffPreparing ||
               $this->state instanceof ReadyForPickup ||
               $this->state instanceof CheckedOut ||
               $this->state instanceof Overdue ||
               $this->state instanceof DropoffScheduled ||
               $this->state instanceof StaffProcessingReturn ||
               $this->state instanceof DamageReported;
    }

    /**
     * Check if loan is overdue.
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->state instanceof Overdue ||
               ($this->is_active && $this->checked_out_at && $this->due_at->isPast());
    }

    /**
     * Check if loan has been returned.
     */
    public function getIsReturnedAttribute(): bool
    {
        return $this->state instanceof Returned && $this->returned_at;
    }

    /**
     * Get number of days equipment has been out.
     */
    public function getDaysOutAttribute(): int
    {
        $endDate = $this->returned_at ?? now();

        return $this->checked_out_at->diffInDays($endDate);
    }

    /**
     * Get number of days overdue (0 if not overdue).
     */
    public function getDaysOverdueAttribute(): int
    {
        $endDate = $this->returned_at ?? now();

        // If returned before due date, not overdue
        if ($endDate <= $this->due_at) {
            return 0;
        }

        return max(0, $this->due_at->diffInDays($endDate));
    }

    /**
     * Mark loan as overdue.
     */
    public function markOverdue(): void
    {
        $this->state->transitionTo(Overdue::class);

        // Update equipment status
        $this->equipment->update(['status' => 'checked_out']);
    }

    /**
     * Process return of equipment.
     */
    public function processReturn(string $conditionIn, ?string $damageNotes = null): void
    {
        $this->update([
            'returned_at' => now(),
            'condition_in' => $conditionIn,
            'damage_notes' => $damageNotes,
        ]);

        $this->state->transitionTo(Returned::class);

        // Update equipment status and condition
        $this->equipment->update([
            'status' => 'available',
            'condition' => $conditionIn,
        ]);
    }

    /**
     * Mark equipment as lost.
     */
    public function markAsLost(?string $notes = null): void
    {
        $this->update([
            'damage_notes' => $notes,
        ]);

        // Note: Lost state would need to be added to state machine
        // For now, we'll use DamageReported or create a Lost state

        // Update equipment status
        $this->equipment->update(['status' => 'retired']);
    }

    /**
     * Calculate total fees for this loan.
     */
    public function getTotalFeesAttribute(): string
    {
        return bcadd($this->security_deposit, $this->rental_fee, 2);
    }

    /**
     * Check if this loan's reservation period overlaps with another period.
     * Adjacent periods (touching at boundaries) are NOT considered overlapping.
     */
    public function overlapsWithPeriod(Period $period): bool
    {
        $thisPeriod = $this->createPeriod();

        // If periods overlap but only touch at boundaries, allow it
        if ($thisPeriod->overlapsWith($period)) {
            // Check if they only touch at boundaries (no actual time overlap)
            $touchesAtStart = $thisPeriod->end()->format('Y-m-d H:i:s') === $period->start()->format('Y-m-d H:i:s');
            $touchesAtEnd = $thisPeriod->start()->format('Y-m-d H:i:s') === $period->end()->format('Y-m-d H:i:s');

            if ($touchesAtStart || $touchesAtEnd) {
                return false; // Allow adjacent periods
            }

            return true; // Real overlap, block it
        }

        return false; // No overlap
    }

    /**
     * Check if the reservation period is currently active.
     */
    public function getIsReservationActiveAttribute(): bool
    {
        $now = now();

        return $now->between($this->reserved_from, $this->due_at);
    }

    /**
     * Check if the reservation period is in the future.
     */
    public function getIsReservationUpcomingAttribute(): bool
    {
        return $this->reserved_from->isFuture();
    }

    /**
     * Check if the reservation period has ended.
     */
    public function getIsReservationExpiredAttribute(): bool
    {
        return $this->due_at->isPast();
    }

    /**
     * Scope for active loans (not yet returned/cancelled).
     */
    public function scopeActive($query)
    {
        return $query->whereState('state', [
            Requested::class,
            StaffPreparing::class,
            ReadyForPickup::class,
            CheckedOut::class,
            Overdue::class,
            DropoffScheduled::class,
            StaffProcessingReturn::class,
            DamageReported::class,
        ]);
    }

    /**
     * Scope for overdue loans.
     */
    public function scopeOverdue($query)
    {
        return $query->whereState('state', Overdue::class)
            ->orWhere(function ($q) {
                $q->whereState('state', [
                    CheckedOut::class,
                    DropoffScheduled::class,
                ])
                    ->where('due_at', '<', now())
                    ->whereNotNull('checked_out_at');
            });
    }

    /**
     * Scope for returned loans.
     */
    public function scopeReturned($query)
    {
        return $query->whereState('state', Returned::class);
    }

    /**
     * Scope for cancelled loans.
     */
    public function scopeCancelled($query)
    {
        return $query->whereState('state', Cancelled::class);
    }

    /**
     * Scope for loans that are currently checked out.
     */
    public function scopeCheckedOut($query)
    {
        return $query->whereState('state', [
            CheckedOut::class,
            Overdue::class,
            DropoffScheduled::class,
        ]);
    }

    /**
     * Scope for loans requiring staff action.
     */
    public function scopeRequiringStaffAction($query)
    {
        return $query->whereState('state', [
            Requested::class,
            StaffPreparing::class,
            StaffProcessingReturn::class,
            DamageReported::class,
        ]);
    }

    /**
     * Scope for loans requiring member action.
     */
    public function scopeRequiringMemberAction($query)
    {
        return $query->whereState('state', [
            ReadyForPickup::class,
            DropoffScheduled::class,
        ]);
    }

    /**
     * Scope for loans by borrower.
     */
    public function scopeByBorrower($query, User $borrower)
    {
        return $query->where('borrower_id', $borrower->id);
    }

    /**
     * Scope for loans of specific equipment.
     */
    public function scopeForEquipment($query, Equipment $equipment)
    {
        return $query->where('equipment_id', $equipment->id);
    }

    /**
     * Scope for loans with active reservations.
     */
    public function scopeWithActiveReservations($query)
    {
        $now = now();

        return $query->where('reserved_from', '<=', $now)
            ->where('due_at', '>=', $now)
            ->active();
    }

    /**
     * Scope for upcoming reservations.
     */
    public function scopeUpcomingReservations($query)
    {
        return $query->where('reserved_from', '>', now())
            ->active();
    }

    /**
     * Scope for reservations that overlap with a given period.
     */
    public function scopeOverlappingPeriod($query, Period $period)
    {
        return $query->where('reserved_from', '<', $period->end())
            ->where('due_at', '>', $period->start())
            ->active();
    }

    /**
     * Scope for reservations on a specific date.
     */
    public function scopeOnDate($query, $date)
    {
        $startOfDay = \Carbon\Carbon::parse($date)->startOfDay();
        $endOfDay = \Carbon\Carbon::parse($date)->endOfDay();

        return $query->where(function ($q) use ($startOfDay, $endOfDay) {
            $q->whereBetween('reserved_from', [$startOfDay, $endOfDay])
                ->orWhereBetween('due_at', [$startOfDay, $endOfDay])
                ->orWhere(function ($q2) use ($startOfDay, $endOfDay) {
                    $q2->where('reserved_from', '<=', $startOfDay)
                        ->where('due_at', '>=', $endOfDay);
                });
        })->active();
    }

    // TODO: Replace LogsActivity trait with domain event-based logging once EquipmentLoan Actions are created
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([])
            ->dontSubmitEmptyLogs();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \CorvMC\Equipment\Database\Factories\EquipmentLoanFactory
    {
        return \CorvMC\Equipment\Database\Factories\EquipmentLoanFactory::new();
    }
}
