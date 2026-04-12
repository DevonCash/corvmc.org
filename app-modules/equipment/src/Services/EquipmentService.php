<?php

namespace CorvMC\Equipment\Services;

use CorvMC\Equipment\Data\CheckoutData;
use CorvMC\Equipment\Data\DamageReportData;
use CorvMC\Equipment\Data\ReservationData;
use CorvMC\Equipment\Data\ReturnData;
use CorvMC\Equipment\Models\Equipment;
use CorvMC\Equipment\Models\EquipmentLoan;
use CorvMC\Equipment\States\EquipmentLoan\CheckedOut;
use CorvMC\Equipment\States\EquipmentLoan\Returned;
use CorvMC\Equipment\States\EquipmentLoan\Reserved;
use CorvMC\Equipment\States\EquipmentLoan\Cancelled;
use Illuminate\Support\Collection;
use Spatie\Period\Period;
use Spatie\Period\Precision;
use App\Models\User;
use Carbon\Carbon;

/**
 * Service class for managing equipment operations.
 * 
 * This service consolidates all equipment-related business logic
 * and provides a clear public API for other modules to interact with.
 */
class EquipmentService
{
    /**
     * Check out equipment to a member.
     */
    public function checkout(CheckoutData $data): EquipmentLoan
    {
        $now = now();
        $period = Period::make($now, $data->dueDate, Precision::MINUTE());

        if (! $this->isAvailableForPeriod($data->equipment, $period)) {
            throw new \Exception('Equipment is not available for checkout.');
        }

        // Create the loan record with immediate checkout
        $loan = EquipmentLoan::create([
            'equipment_id' => $data->equipment->id,
            'borrower_id' => $data->borrower->id,
            'reserved_from' => $now,
            'checked_out_at' => $now,
            'due_at' => $data->dueDate,
            'condition_out' => $data->conditionOut,
            'security_deposit' => $data->securityDeposit,
            'rental_fee' => $data->rentalFee,
            'notes' => $data->notes,
            'state' => CheckedOut::class,
        ]);

        // Update equipment status
        $data->equipment->update(['status' => 'checked_out']);

        $this->logActivity('checkout', $data->equipment, $data->borrower, [
            'loan_id' => $loan->id,
            'due_at' => $data->dueDate->toDateTimeString(),
        ]);

        return $loan;
    }

    /**
     * Return equipment from a loan.
     */
    public function return(ReturnData $data): EquipmentLoan
    {
        if ($data->loan->state !== CheckedOut::class) {
            throw new \Exception('Equipment is not checked out.');
        }

        $data->loan->update([
            'returned_at' => now(),
            'condition_in' => $data->conditionIn,
            'return_notes' => $data->returnNotes,
            'state' => Returned::class,
        ]);

        // Update equipment status
        $data->loan->equipment->update(['status' => 'available']);

        $this->logActivity('return', $data->loan->equipment, auth()->user(), [
            'loan_id' => $data->loan->id,
            'condition_in' => $data->conditionIn,
        ]);

        return $data->loan;
    }

    /**
     * Reserve equipment for future checkout.
     */
    public function reserve(ReservationData $data): EquipmentLoan
    {
        $period = Period::make($data->reserveFrom, $data->dueDate, Precision::MINUTE());

        if (! $this->isAvailableForPeriod($data->equipment, $period)) {
            throw new \Exception('Equipment is not available for the requested period.');
        }

        $loan = EquipmentLoan::create([
            'equipment_id' => $data->equipment->id,
            'borrower_id' => $data->borrower->id,
            'reserved_from' => $data->reserveFrom,
            'due_at' => $data->dueDate,
            'notes' => $data->notes,
            'state' => Reserved::class,
        ]);

        $this->logActivity('reserve', $data->equipment, $data->borrower, [
            'loan_id' => $loan->id,
            'reserved_from' => $data->reserveFrom->toDateTimeString(),
            'due_at' => $data->dueDate->toDateTimeString(),
        ]);

        return $loan;
    }

    /**
     * Cancel a reservation or loan.
     */
    public function cancel(EquipmentLoan $loan, ?string $reason = null): void
    {
        $previousState = $loan->state;

        $loan->update([
            'state' => Cancelled::class,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        // If equipment was checked out, update its status
        if ($previousState === CheckedOut::class) {
            $loan->equipment->update(['status' => 'available']);
        }

        $this->logActivity('cancel', $loan->equipment, auth()->user(), [
            'loan_id' => $loan->id,
            'reason' => $reason,
            'previous_state' => class_basename($previousState),
        ]);
    }

    /**
     * Record damage to equipment.
     */
    public function recordDamage(DamageReportData $data): void
    {
        // Update equipment status if severe damage
        if ($data->isSevere()) {
            $data->equipment->update(['status' => 'maintenance']);
        }

        $this->logActivity('damage_report', $data->equipment, $data->reporter, [
            'severity' => $data->severity,
            'description' => $data->description,
        ]);

        // TODO: Create actual damage report record when model is available
        // EquipmentDamageReport::create([...]);
    }

    /**
     * Get active loans for a user.
     */
    public function getActiveLoansForUser(User $user): Collection
    {
        return EquipmentLoan::forBorrower($user)
            ->active()
            ->with('equipment')
            ->get();
    }

    /**
     * Get overdue loans.
     */
    public function getOverdueLoans(): Collection
    {
        return EquipmentLoan::overdue()
            ->with(['equipment', 'borrower'])
            ->get();
    }

    /**
     * Check if equipment is available for a specific period.
     */
    public function isAvailableForPeriod(Equipment $equipment, Period $period): bool
    {
        // Check basic equipment availability
        if (! $equipment->loanable) {
            return false;
        }

        // Allow reservations if equipment is available or will be returned before the period
        if (! in_array($equipment->status, ['available', 'checked_out'])) {
            return false;
        }

        return ! $this->hasConflictingReservations($equipment, $period);
    }

    /**
     * Get availability calendar for equipment.
     */
    public function getAvailabilityCalendar(Equipment $equipment, Carbon $from, Carbon $to): array
    {
        $loans = EquipmentLoan::forEquipment($equipment)
            ->active()
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('reserved_from', [$from, $to])
                      ->orWhereBetween('due_at', [$from, $to])
                      ->orWhere(function ($q) use ($from, $to) {
                          $q->where('reserved_from', '<=', $from)
                            ->where('due_at', '>=', $to);
                      });
            })
            ->get();

        return [
            'equipment' => $equipment->only(['id', 'name', 'type']),
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'reservations' => $loans->map(fn($loan) => [
                'id' => $loan->id,
                'from' => $loan->reserved_from->toDateTimeString(),
                'to' => $loan->due_at->toDateTimeString(),
                'borrower' => $loan->borrower->name,
                'status' => class_basename($loan->state),
            ])->toArray(),
            'is_available' => $equipment->loanable && $equipment->status === 'available',
        ];
    }

    /**
     * Check if equipment has conflicting reservations for a given period.
     */
    protected function hasConflictingReservations(Equipment $equipment, Period $period): bool
    {
        $activeLoans = EquipmentLoan::forEquipment($equipment)
            ->active()
            ->get();

        return $activeLoans->contains(function ($loan) use ($period) {
            return $loan->overlapsWithPeriod($period);
        });
    }

    /**
     * Mark equipment as returned to the original owner.
     */
    public function markReturnedToOwner(Equipment $equipment): void
    {
        $equipment->update([
            'ownership_status' => 'returned_to_owner',
            'status' => 'retired',
        ]);

        $this->logActivity('returned_to_owner', $equipment, auth()->user(), [
            'ownership_status' => 'returned_to_owner',
        ]);
    }

    /**
     * Get comprehensive equipment statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total_equipment' => Equipment::count(),
            'available_equipment' => Equipment::available()->count(),
            'checked_out_equipment' => Equipment::where('status', 'checked_out')->count(),
            'maintenance_equipment' => Equipment::where('status', 'maintenance')->count(),
            'active_loans' => EquipmentLoan::active()->count(),
            'overdue_loans' => EquipmentLoan::overdue()->count(),
            'donated_equipment' => Equipment::donated()->count(),
            'loaned_to_cmc' => Equipment::onLoanToCmc()->count(),
        ];
    }

    /**
     * Mark an equipment loan as overdue.
     */
    public function markOverdue(EquipmentLoan $loan): void
    {
        $loan->markOverdue();
        
        $this->logActivity('marked_overdue', $loan->equipment, auth()->user(), [
            'loan_id' => $loan->id,
            'borrower' => $loan->borrower->name,
            'due_at' => $loan->due_at->toDateTimeString(),
        ]);
    }

    /**
     * Process the return of equipment with condition assessment.
     */
    public function processReturn(
        EquipmentLoan $loan,
        string $conditionIn,
        ?string $damageNotes = null
    ): EquipmentLoan {
        $loan->processReturn($conditionIn, $damageNotes);

        $this->logActivity('equipment_returned', $loan->equipment, auth()->user(), [
            'loan_id' => $loan->id,
            'condition_in' => $conditionIn,
            'damage_notes' => $damageNotes,
        ]);

        return $loan->fresh();
    }

    /**
     * Calculate total value of equipment by acquisition type.
     */
    public function getValueByAcquisitionType(): array
    {
        return Equipment::selectRaw('acquisition_type, SUM(estimated_value) as total_value')
            ->whereNotNull('estimated_value')
            ->groupBy('acquisition_type')
            ->pluck('total_value', 'acquisition_type')
            ->toArray();
    }

    /**
     * Log activity for equipment operations.
     */
    protected function logActivity(string $event, Equipment $equipment, ?User $user, array $properties = []): void
    {
        activity('equipment')
            ->performedOn($equipment)
            ->causedBy($user)
            ->event($event)
            ->withProperties($properties)
            ->log(match($event) {
                'checkout' => "Equipment checked out to {$user?->name}",
                'return' => 'Equipment returned',
                'reserve' => "Equipment reserved by {$user?->name}",
                'cancel' => 'Loan cancelled',
                'damage_report' => 'Damage reported',
                'returned_to_owner' => 'Equipment returned to original owner',
                'marked_overdue' => 'Loan marked as overdue',
                'equipment_returned' => 'Equipment returned with condition assessment',
                default => $event,
            });
    }
}