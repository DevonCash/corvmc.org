<?php

namespace CorvMC\Finance\Listeners;

use App\Enums\CreditType;
use Brick\Money\Money;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\Finance\Actions\Pricing\CalculatePriceForUser;
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

        // Check if credits should be deferred (e.g., for Reserved status)
        $deferCredits = $event->deferCredits ?? false;

        $user = $chargeable->getBillableUser();

        DB::transaction(function () use ($chargeable, $user, $deferCredits) {
            // Calculate price with credit application
            $pricing = CalculatePriceForUser::run($chargeable, $user);

            // Create Charge record
            $charge = Charge::createForChargeable(
                $chargeable,
                $pricing->amount,
                $pricing->net_amount,
                $pricing->credits_applied ?: null
            );

            // Determine initial status
            if ($pricing->net_amount === 0) {
                // Fully covered by credits - mark as paid (no payment needed)
                $charge->update([
                    'status' => ChargeStatus::Paid,
                    'payment_method' => 'credits',
                    'paid_at' => now(),
                ]);
            }

            // Update legacy fields on reservation for backward compatibility
            // TODO: Remove this once Step 7 migration is complete
            $this->updateLegacyFields($chargeable, $pricing, $charge);

            // Deduct credits if not deferred
            if (! $deferCredits && ! empty($pricing->credits_applied)) {
                $this->deductCredits($user, $pricing->credits_applied, $chargeable);
            }
        });
    }

    /**
     * Update legacy payment fields on reservation for backward compatibility.
     *
     * @param  Chargeable&Model  $chargeable
     * @param  \CorvMC\Finance\Data\PriceCalculationData  $pricing
     * @param  Charge  $charge
     */
    protected function updateLegacyFields($chargeable, $pricing, Charge $charge): void
    {
        if (! $chargeable instanceof RehearsalReservation) {
            return;
        }

        // Calculate free hours from credits applied
        $freeHoursBlocks = $pricing->credits_applied['free_hours'] ?? 0;
        $minutesPerBlock = config('finance.credits.minutes_per_block', 30);
        $freeHours = ($freeHoursBlocks * $minutesPerBlock) / 60;

        // Update reservation with legacy payment fields
        // Note: cost field uses MoneyCast which expects Money object or dollar amount
        $chargeable->updateQuietly([
            'cost' => Money::ofMinor($pricing->net_amount, 'USD'),
            'free_hours_used' => $freeHours,
            'payment_status' => $pricing->net_amount === 0 ? 'n/a' : 'unpaid',
        ]);
    }

    /**
     * Deduct credits from user based on pricing calculation.
     *
     * @param  \App\Models\User  $user
     * @param  array<string, int>  $creditsApplied
     * @param  Chargeable&Model  $chargeable
     */
    protected function deductCredits($user, array $creditsApplied, $chargeable): void
    {
        foreach ($creditsApplied as $creditTypeKey => $blocks) {
            if ($blocks > 0) {
                $creditType = CreditType::from($creditTypeKey);
                $user->deductCredit(
                    $blocks,
                    $creditType,
                    'charge_usage',
                    $chargeable->charge?->id
                );
            }
        }
    }
}
