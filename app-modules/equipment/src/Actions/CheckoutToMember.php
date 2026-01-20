<?php

namespace CorvMC\Equipment\Actions;

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Equipment\Models\Equipment;
use CorvMC\Equipment\Models\EquipmentLoan;
use CorvMC\Equipment\States\EquipmentLoan\CheckedOut;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Period\Period;
use Spatie\Period\Precision;

class CheckoutToMember
{
    use AsAction;

    /**
     * Checkout equipment to a member (immediate checkout).
     */
    public function handle(
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

        if (! $this->isAvailableForPeriod($equipment, $period)) {
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
            'state' => CheckedOut::class,
        ]);

        // Update equipment status
        $equipment->update(['status' => 'checked_out']);

        return $loan;
    }

    /**
     * Check if equipment is available for a specific reservation period.
     */
    private function isAvailableForPeriod(Equipment $equipment, Period $period): bool
    {
        // Check basic equipment availability - must be loanable and available status
        if (! $equipment->loanable || $equipment->status !== 'available') {
            return false;
        }

        return ! $this->hasConflictingReservations($equipment, $period);
    }

    /**
     * Check if equipment has conflicting reservations for a given period.
     */
    private function hasConflictingReservations(Equipment $equipment, Period $period): bool
    {
        $activeLoans = EquipmentLoan::forEquipment($equipment)
            ->active()
            ->get();

        return $activeLoans->contains(function ($loan) use ($period) {
            return $loan->overlapsWithPeriod($period);
        });
    }
}
