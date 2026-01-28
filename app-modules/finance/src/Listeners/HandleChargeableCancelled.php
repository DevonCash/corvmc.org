<?php

namespace CorvMC\Finance\Listeners;

use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Contracts\Chargeable;
use CorvMC\Finance\Enums\ChargeStatus;
use CorvMC\Finance\Models\Charge;
use CorvMC\Finance\Models\CreditTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Handle chargeable cancellation events.
 *
 * Refunds credits and updates Charge status when a chargeable
 * (like a reservation) is cancelled.
 */
class HandleChargeableCancelled
{
    /**
     * Handle the event.
     *
     * @param  object  $event  Event with 'chargeable' and 'originalStatus' properties
     */
    public function handle(object $event): void
    {
        /** @var Chargeable&Model $chargeable */
        $chargeable = $event->chargeable;
        $originalStatus = $event->originalStatus ?? null;

        // Find the charge for this chargeable (use morph class for polymorphic lookup)
        $charge = Charge::where('chargeable_type', $chargeable->getMorphClass())
            ->where('chargeable_id', $chargeable->getKey())
            ->first();

        if (! $charge) {
            // No charge exists - nothing to do
            return;
        }

        $user = $chargeable->getBillableUser();

        DB::transaction(function () use ($chargeable, $charge, $user, $originalStatus) {
            // Refund credits if they were deducted
            // Credits are only deducted for non-deferred statuses (e.g., not 'reserved')
            $shouldRefund = $this->shouldRefundCredits($originalStatus);

            if ($shouldRefund && ! empty($charge->credits_applied)) {
                $this->refundCredits($user, $charge);
            }

            // Update charge status
            $charge->markAsRefunded('Cancelled');
        });
    }

    /**
     * Determine if credits should be refunded based on original status.
     *
     * @param  mixed  $originalStatus  The status before cancellation
     */
    protected function shouldRefundCredits($originalStatus): bool
    {
        // If no original status provided, assume credits were deducted
        if ($originalStatus === null) {
            return true;
        }

        // For reservation statuses, 'reserved' status doesn't have credits deducted
        if (is_object($originalStatus) && property_exists($originalStatus, 'value')) {
            return $originalStatus->value !== 'reserved';
        }

        if (is_string($originalStatus)) {
            return $originalStatus !== 'reserved';
        }

        return true;
    }

    /**
     * Refund credits to user based on charge record.
     *
     * @param  \App\Models\User  $user
     * @param  Charge  $charge
     */
    protected function refundCredits($user, Charge $charge): void
    {
        // Look for original deduction transaction linked to this charge
        $deductions = CreditTransaction::where('user_id', $user->id)
            ->where('source', 'charge_usage')
            ->where('source_id', $charge->id)
            ->where('amount', '<', 0)
            ->get();

        foreach ($deductions as $deduction) {
            $creditType = CreditType::from($deduction->credit_type);
            $blocksToRefund = abs($deduction->amount);

            $user->addCredit(
                $blocksToRefund,
                $creditType,
                'charge_cancellation',
                $charge->id,
                "Refund for cancelled charge #{$charge->id}"
            );
        }
    }
}
