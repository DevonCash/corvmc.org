<?php

namespace CorvMC\Finance\Listeners;

use App\Enums\CreditType;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Brick\Money\Money;
use CorvMC\Finance\Actions\Pricing\CalculatePriceForUser;
use CorvMC\Finance\Contracts\Chargeable;
use CorvMC\Finance\Enums\ChargeStatus;
use CorvMC\Finance\Models\Charge;
use CorvMC\Finance\Models\CreditTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Handle chargeable update events.
 *
 * Recalculates pricing, adjusts credits, and updates Charge record
 * when a chargeable (like a reservation) is modified.
 */
class HandleChargeableUpdated
{
    /**
     * Handle the event.
     *
     * @param  object  $event  Event with 'chargeable' and 'oldBillableUnits' properties
     */
    public function handle(object $event): void
    {
        /** @var Chargeable&Model $chargeable */
        $chargeable = $event->chargeable;
        $oldBillableUnits = $event->oldBillableUnits ?? null;

        // Find the charge for this chargeable
        $charge = Charge::where('chargeable_type', get_class($chargeable))
            ->where('chargeable_id', $chargeable->getKey())
            ->first();

        if (! $charge) {
            // No charge exists - create one
            $createdEvent = new \stdClass;
            $createdEvent->chargeable = $chargeable;
            $createdEvent->deferCredits = false;
            (new HandleChargeableCreated)->handle($createdEvent);

            return;
        }

        $user = $chargeable->getBillableUser();

        DB::transaction(function () use ($chargeable, $charge, $user) {
            // Calculate new pricing
            $pricing = CalculatePriceForUser::run($chargeable, $user);

            // Get previously applied credits
            $oldCreditsApplied = $charge->credits_applied ?? [];

            // Adjust credits if changed
            $this->adjustCredits($user, $charge, $oldCreditsApplied, $pricing->credits_applied);

            // Update charge record
            $charge->update([
                'amount' => $pricing->amount,
                'credits_applied' => $pricing->credits_applied ?: null,
                'net_amount' => $pricing->net_amount,
            ]);

            // Update status if fully covered by credits
            if ($pricing->net_amount === 0 && $charge->status->isPending()) {
                $charge->update([
                    'status' => ChargeStatus::Paid,
                    'payment_method' => 'credits',
                    'paid_at' => now(),
                ]);
            } elseif ($pricing->net_amount > 0 && $charge->status->isPaid() && $charge->payment_method === 'credits') {
                // Was fully covered by credits but now requires payment
                $charge->update([
                    'status' => ChargeStatus::Pending,
                    'payment_method' => null,
                    'paid_at' => null,
                ]);
            }

            // Update legacy fields for backward compatibility
            $this->updateLegacyFields($chargeable, $pricing);
        });
    }

    /**
     * Update legacy payment fields on reservation for backward compatibility.
     *
     * @param  Chargeable&Model  $chargeable
     * @param  \CorvMC\Finance\Data\PriceCalculationData  $pricing
     */
    protected function updateLegacyFields($chargeable, $pricing): void
    {
        if (! $chargeable instanceof RehearsalReservation) {
            return;
        }

        // Calculate free hours from credits applied
        $freeHoursBlocks = $pricing->credits_applied['free_hours'] ?? 0;
        $minutesPerBlock = config('finance.credits.minutes_per_block', 30);
        $freeHours = ($freeHoursBlocks * $minutesPerBlock) / 60;

        // Update reservation with legacy payment fields
        $chargeable->updateQuietly([
            'cost' => Money::ofMinor($pricing->net_amount, 'USD'),
            'free_hours_used' => $freeHours,
            'payment_status' => $pricing->net_amount === 0
                ? 'n/a'
                : ($chargeable->payment_status ?? 'unpaid'),
        ]);
    }

    /**
     * Adjust credits based on difference between old and new credit application.
     *
     * @param  \App\Models\User  $user
     * @param  Charge  $charge
     * @param  array<string, int>  $oldCredits
     * @param  array<string, int>  $newCredits
     */
    protected function adjustCredits($user, Charge $charge, array $oldCredits, array $newCredits): void
    {
        // Get all credit types from both old and new
        $allTypes = array_unique(array_merge(array_keys($oldCredits), array_keys($newCredits)));

        foreach ($allTypes as $creditTypeKey) {
            $oldBlocks = $oldCredits[$creditTypeKey] ?? 0;
            $newBlocks = $newCredits[$creditTypeKey] ?? 0;
            $difference = $newBlocks - $oldBlocks;

            if ($difference === 0) {
                continue;
            }

            $creditType = CreditType::from($creditTypeKey);

            if ($difference > 0) {
                // Need to deduct more credits
                $user->deductCredit(
                    $difference,
                    $creditType,
                    'charge_update',
                    $charge->id
                );
            } else {
                // Refund credits
                $user->addCredit(
                    abs($difference),
                    $creditType,
                    'charge_update',
                    $charge->id,
                    'Refund from charge update'
                );
            }
        }
    }
}
