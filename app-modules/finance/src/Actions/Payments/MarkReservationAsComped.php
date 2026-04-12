<?php

namespace CorvMC\Finance\Actions\Payments;

use App\Filament\Actions\Payment\ChargeableMarkCompedAction;
use CorvMC\Finance\Data\CompData;
use CorvMC\Finance\Services\PaymentService;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use PaymentService::recordComp() instead
 * 
 * This action is maintained for backward compatibility.
 * New code should use the PaymentService directly.
 */
class MarkReservationAsComped
{
    use AsAction;

    /**
     * @deprecated Use PaymentService::recordComp() instead
     */
    public function handle(RehearsalReservation $reservation, ?string $notes = null): void
    {
        // Create DTO from parameters
        $compData = CompData::from([
            'chargeable' => $reservation,
            'reason' => $notes ?? 'No reason provided',
            'authorizedBy' => auth()->user()?->name,
            'notes' => $notes,
        ]);

        // Delegate to service
        app(PaymentService::class)->recordComp($compData);
    }

    /**
     * @deprecated Use ChargeableMarkCompedAction::make() instead
     */
    public static function filamentAction(): \Filament\Actions\Action
    {
        return ChargeableMarkCompedAction::make();
    }

    /**
     * @deprecated Use ChargeableMarkCompedAction::bulkAction() instead
     */
    public static function filamentBulkAction(): \Filament\Actions\Action
    {
        return ChargeableMarkCompedAction::bulkAction();
    }
}
