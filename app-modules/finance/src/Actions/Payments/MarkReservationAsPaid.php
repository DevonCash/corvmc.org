<?php

namespace CorvMC\Finance\Actions\Payments;

use App\Filament\Actions\Payment\ChargeableMarkPaidAction;
use CorvMC\Finance\Data\PaymentData;
use CorvMC\Finance\Services\PaymentService;
use CorvMC\SpaceManagement\Models\RehearsalReservation;

/**
 * @deprecated Use PaymentService::recordPayment() instead
 * 
 * This action is maintained for backward compatibility.
 * New code should use the PaymentService directly.
 */
class MarkReservationAsPaid
{
    /**
     * @deprecated Use PaymentService::recordPayment() instead
     */
    public function handle(RehearsalReservation $reservation, ?string $paymentMethod = null, ?string $notes = null): void
    {
        // Create DTO from parameters
        $paymentData = PaymentData::from([
            'chargeable' => $reservation,
            'paymentMethod' => $paymentMethod ?? 'manual',
            'notes' => $notes,
        ]);

        // Delegate to service
        app(PaymentService::class)->recordPayment($paymentData);
    }

    /**
     * @deprecated Use ChargeableMarkPaidAction::make() instead
     */
    public static function filamentAction(): \Filament\Actions\Action
    {
        return ChargeableMarkPaidAction::make();
    }

    /**
     * @deprecated Use ChargeableMarkPaidAction::bulkAction() instead
     */
    public static function filamentBulkAction(): \Filament\Actions\Action
    {
        return ChargeableMarkPaidAction::bulkAction();
    }
}
