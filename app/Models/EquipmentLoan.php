<?php

namespace App\Models;

use App\States\EquipmentLoan\EquipmentLoanState;
use App\States\EquipmentLoan\Requested;
use App\States\EquipmentLoan\StaffPreparing;
use App\States\EquipmentLoan\ReadyForPickup;
use App\States\EquipmentLoan\CheckedOut;
use App\States\EquipmentLoan\Overdue;
use App\States\EquipmentLoan\DropoffScheduled;
use App\States\EquipmentLoan\StaffProcessingReturn;
use App\States\EquipmentLoan\Returned;
use App\States\EquipmentLoan\DamageReported;
use App\States\EquipmentLoan\Cancelled;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\HasStates;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Period\Period;
use Spatie\Period\Precision;

/**
 * Represents a loan of equipment from CMC to a member.
 * 
 * Tracks the checkout/return process, condition, and financial aspects
 * of equipment loans to members.
 */
class EquipmentLoan extends Model
{
    use HasFactory, LogsActivity, HasStates;

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
    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * Get the user borrowing the equipment.
     */
    public function borrower()
    {
        return $this->belongsTo(User::class, 'borrower_id');
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
     * Get the reservation period for this loan.
     */
    public function getReservationPeriod(): Period
    {
        return Period::make($this->reserved_from, $this->due_at, Precision::MINUTE());
    }

    /**
     * Check if this loan's reservation period overlaps with another period.
     * Adjacent periods (touching at boundaries) are NOT considered overlapping.
     */
    public function overlapsWithPeriod(Period $period): bool
    {
        $thisPeriod = $this->getReservationPeriod();
        
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['state', 'due_at', 'returned_at', 'condition_in'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Equipment loan {$eventName}");
    }
}