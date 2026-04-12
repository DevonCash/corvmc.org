<?php

namespace CorvMC\Equipment\Actions;

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Equipment\Data\CheckoutData;
use CorvMC\Equipment\Models\Equipment;
use CorvMC\Equipment\Models\EquipmentLoan;
use CorvMC\Equipment\Services\EquipmentService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use EquipmentService::checkout() instead
 * 
 * This action is maintained for backward compatibility.
 * New code should use the EquipmentService directly.
 */
class CheckoutToMember
{
    use AsAction;

    /**
     * Checkout equipment to a member (immediate checkout).
     * 
     * @deprecated Use EquipmentService::checkout() instead
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
        // Create DTO from parameters
        $checkoutData = CheckoutData::from([
            'equipment' => $equipment,
            'borrower' => $borrower,
            'dueDate' => $dueDate,
            'conditionOut' => $conditionOut,
            'securityDeposit' => $securityDeposit,
            'rentalFee' => $rentalFee,
            'notes' => $notes,
        ]);

        // Delegate to service
        return app(EquipmentService::class)->checkout($checkoutData);
    }
}
