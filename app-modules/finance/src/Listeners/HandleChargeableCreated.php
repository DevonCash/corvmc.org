<?php

namespace CorvMC\Finance\Listeners;

use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Facades\PricingService;
use CorvMC\Finance\Contracts\Chargeable;
use CorvMC\Finance\Enums\ChargeStatus;
use CorvMC\Finance\Models\Charge;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Handle chargeable creation events.
 *
 * Creates a Charge record and deducts credits when a chargeable
 * (like a reservation) is created.
 *
 * This listener runs synchronously within the caller's transaction,
 * ensuring atomicity - if charge creation or credit deduction fails,
 * the entire operation rolls back.
 */
class HandleChargeableCreated
{
    /**
     * Handle the event.
     *
     * @param  object  $event  Event with 'chargeable' and optional 'deferCredits' properties
     */
    public function handle(object $event): void
    {
        /** @var Chargeable&Model $chargeable */
        $chargeable = $event->chargeable;

        $user = $chargeable->getBillableUser();

        DB::transaction(function () use ($chargeable, $user) {
            // Calculate price with credit application
            $pricing = PricingService::calculatePriceForUser($chargeable, $user);

            // Create Charge record
            $charge = Charge::createForChargeable(
                $chargeable,
                $pricing->amount,
                $pricing->net_amount,
            );

            // Determine initial status
            if ($pricing->net_amount === 0) {
                // Fully covered by credits - no monetary payment needed
                $charge->markAsCoveredByCredits();
            }

        });
    }
}
