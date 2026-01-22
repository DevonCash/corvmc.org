<?php

namespace CorvMC\Finance\Listeners;

use App\Enums\CreditType;
use CorvMC\Finance\Contracts\Chargeable;
use CorvMC\Finance\Models\Charge;
use CorvMC\Finance\Models\CreditTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Handle chargeable confirmation events.
 *
 * Deducts credits that were deferred at creation time.
 * Used when a "Reserved" status chargeable becomes "Confirmed".
 */
class HandleChargeableConfirmed
{
    /**
     * Handle the event.
     *
     * @param  object  $event  Event with 'chargeable' and 'previousStatus' properties
     */
    public function handle(object $event): void
    {
        /** @var Chargeable&Model $chargeable */
        $chargeable = $event->chargeable;
        $previousStatus = $event->previousStatus ?? null;

        // Find the charge for this chargeable
        $charge = Charge::where('chargeable_type', get_class($chargeable))
            ->where('chargeable_id', $chargeable->getKey())
            ->first();

        if (! $charge) {
            // No charge exists - nothing to do
            return;
        }

        // Check if credits were already deducted
        $creditsAlreadyDeducted = CreditTransaction::where('source', 'charge_usage')
            ->where('source_id', $charge->id)
            ->exists();

        if ($creditsAlreadyDeducted) {
            // Credits were already deducted - nothing to do
            return;
        }

        $user = $chargeable->getBillableUser();

        // Deduct the credits that were deferred
        if (! empty($charge->credits_applied)) {
            DB::transaction(function () use ($user, $charge) {
                foreach ($charge->credits_applied as $creditTypeKey => $blocks) {
                    if ($blocks > 0) {
                        $creditType = CreditType::from($creditTypeKey);
                        $user->deductCredit(
                            $blocks,
                            $creditType,
                            'charge_usage',
                            $charge->id
                        );
                    }
                }
            });
        }
    }
}
