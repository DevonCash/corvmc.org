<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Period\Period;
use Spatie\Period\Precision;

/**
 * Service for managing equipment operations in the CMC gear lending library.
 * 
 * Handles equipment availability, loan management, and automation features.
 */
class EquipmentService
{
    /**
     * Check if equipment is available for checkout.
     */
    public function isAvailable(Equipment $equipment): bool
    {
        return $equipment->is_available;
    }

    /**
     * Check if equipment is available for a specific reservation period.
     */
    public function isAvailableForPeriod(Equipment $equipment, Period $period): bool
    {
        // Check basic equipment availability - must be loanable and available status
        if (!$equipment->loanable || $equipment->status !== 'available') {
            return false;
        }

        return !$this->hasConflictingReservations($equipment, $period);
    }

    /**
     * Check if equipment has conflicting reservations for a given period.
     */
    public function hasConflictingReservations(Equipment $equipment, Period $period): bool
    {
        $activeLoans = EquipmentLoan::forEquipment($equipment)
            ->active()
            ->get();
            
        return $activeLoans->contains(function ($loan) use ($period) {
            return $loan->overlapsWithPeriod($period);
        });
    }

    /**
     * Get all available equipment by type.
     */
    public function getAvailableByType(string $type): Collection
    {
        return Equipment::available()
            ->ofType($type)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all available equipment.
     */
    public function getAllAvailable(): Collection
    {
        return Equipment::available()
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a reservation for equipment.
     */
    public function createReservation(
        Equipment $equipment,
        User $borrower,
        Carbon $reservedFrom,
        Carbon $dueDate,
        float $securityDeposit = 0,
        float $rentalFee = 0,
        ?string $notes = null
    ): EquipmentLoan {
        $period = Period::make($reservedFrom, $dueDate, Precision::MINUTE());
        
        if (!$this->isAvailableForPeriod($equipment, $period)) {
            throw new \Exception('Equipment is not available for the requested period.');
        }

        // Create the reservation record
        $loan = EquipmentLoan::create([
            'equipment_id' => $equipment->id,
            'borrower_id' => $borrower->id,
            'reserved_from' => $reservedFrom,
            'due_at' => $dueDate,
            'security_deposit' => $securityDeposit,
            'rental_fee' => $rentalFee,
            'notes' => $notes,
        ]);

        return $loan;
    }

    /**
     * Checkout equipment to a member (immediate checkout).
     */
    public function checkoutToMember(
        Equipment $equipment,
        User $borrower,
        Carbon $dueDate,
        string $conditionOut = 'good',
        float $securityDeposit = 0,
        float $rentalFee = 0,
        ?string $notes = null
    ): EquipmentLoan {
        $now = now();
        $period = Period::make($now, $dueDate, Precision::MINUTE());
        
        if (!$this->isAvailableForPeriod($equipment, $period)) {
            throw new \Exception('Equipment is not available for checkout.');
        }

        // Create the loan record with immediate checkout
        $loan = EquipmentLoan::create([
            'equipment_id' => $equipment->id,
            'borrower_id' => $borrower->id,
            'reserved_from' => $now,
            'checked_out_at' => $now,
            'due_at' => $dueDate,
            'condition_out' => $conditionOut,
            'security_deposit' => $securityDeposit,
            'rental_fee' => $rentalFee,
            'notes' => $notes,
            'state' => \App\States\EquipmentLoan\CheckedOut::class,
        ]);

        // Update equipment status
        $equipment->update(['status' => 'checked_out']);

        return $loan;
    }

    /**
     * Process return of equipment.
     */
    public function processReturn(
        EquipmentLoan $loan,
        string $conditionIn,
        ?string $damageNotes = null
    ): EquipmentLoan {
        $loan->processReturn($conditionIn, $damageNotes);
        
        return $loan->fresh();
    }

    /**
     * Mark equipment loan as overdue.
     */
    public function markOverdue(EquipmentLoan $loan): void
    {
        $loan->markOverdue();
    }

    /**
     * Get all overdue loans.
     */
    public function getOverdueLoans(): Collection
    {
        return EquipmentLoan::overdue()
            ->with(['equipment', 'borrower'])
            ->orderBy('due_at')
            ->get();
    }

    /**
     * Get active loans for a specific borrower.
     */
    public function getActiveLoansForUser(User $user): Collection
    {
        return EquipmentLoan::active()
            ->byBorrower($user)
            ->with('equipment')
            ->orderBy('due_at')
            ->get();
    }

    /**
     * Get loan history for specific equipment.
     */
    public function getLoanHistoryForEquipment(Equipment $equipment): Collection
    {
        return EquipmentLoan::forEquipment($equipment)
            ->with('borrower')
            ->orderByDesc('checked_out_at')
            ->get();
    }

    /**
     * Check if user has any overdue loans.
     */
    public function userHasOverdueLoans(User $user): bool
    {
        return EquipmentLoan::overdue()
            ->byBorrower($user)
            ->exists();
    }

    /**
     * Get equipment that needs to be returned to original owner.
     */
    public function getEquipmentNeedingReturn(): Collection
    {
        return Equipment::onLoanToCmc()
            ->where('ownership_status', 'on_loan_to_cmc')
            ->where('return_due_date', '<=', now())
            ->with('provider')
            ->get();
    }

    /**
     * Mark equipment as returned to original owner.
     */
    public function markReturnedToOwner(Equipment $equipment): void
    {
        $equipment->update([
            'ownership_status' => 'returned_to_owner',
            'status' => 'retired',
        ]);
    }

    /**
     * Get equipment donated by a specific user.
     */
    public function getDonatedByUser(User $user): Collection
    {
        return Equipment::donated()
            ->where('provider_id', $user->id)
            ->orderByDesc('acquisition_date')
            ->get();
    }

    /**
     * Get equipment statistics.
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
     * Process daily automation tasks.
     */
    public function processDailyTasks(): array
    {
        $results = [];

        // Mark overdue loans
        $overdueCount = 0;
        EquipmentLoan::active()
            ->where('due_at', '<', now())
            ->each(function ($loan) use (&$overdueCount) {
                $this->markOverdue($loan);
                $overdueCount++;
            });

        $results['marked_overdue'] = $overdueCount;

        // Check for equipment needing return
        $needingReturn = $this->getEquipmentNeedingReturn();
        $results['equipment_needing_return'] = $needingReturn->count();

        return $results;
    }

    /**
     * Get popular equipment (most borrowed).
     */
    public function getPopularEquipment(int $limit = 10): Collection
    {
        return Equipment::withCount('loans')
            ->orderByDesc('loans_count')
            ->limit($limit)
            ->get();
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
     * Get upcoming reservations for a specific date.
     */
    public function getReservationsForDate(Carbon $date): Collection
    {
        return EquipmentLoan::onDate($date)
            ->with(['equipment', 'borrower'])
            ->orderBy('reserved_from')
            ->get();
    }

    /**
     * Get all upcoming reservations for a user.
     */
    public function getUpcomingReservationsForUser(User $user): Collection
    {
        return EquipmentLoan::upcomingReservations()
            ->byBorrower($user)
            ->with('equipment')
            ->orderBy('reserved_from')
            ->get();
    }

    /**
     * Get all active reservations for a user.
     */
    public function getActiveReservationsForUser(User $user): Collection
    {
        return EquipmentLoan::withActiveReservations()
            ->byBorrower($user)
            ->with('equipment')
            ->orderBy('reserved_from')
            ->get();
    }

    /**
     * Get all reservations for specific equipment.
     */
    public function getReservationsForEquipment(Equipment $equipment): Collection
    {
        return EquipmentLoan::forEquipment($equipment)
            ->active()
            ->with('borrower')
            ->orderBy('reserved_from')
            ->get();
    }

    /**
     * Get equipment available during a specific period.
     */
    public function getAvailableEquipmentForPeriod(Period $period, ?string $type = null): Collection
    {
        $query = Equipment::available();
        
        if ($type) {
            $query->ofType($type);
        }

        return $query->get()->filter(function ($equipment) use ($period) {
            return $this->isAvailableForPeriod($equipment, $period);
        });
    }

    /**
     * Cancel a reservation (only if not yet checked out).
     */
    public function cancelReservation(EquipmentLoan $loan): bool
    {
        if ($loan->checked_out_at) {
            throw new \Exception('Cannot cancel reservation - equipment has already been checked out.');
        }

        $loan->state->transitionTo(\App\States\EquipmentLoan\Cancelled::class);
        return true;
    }
}