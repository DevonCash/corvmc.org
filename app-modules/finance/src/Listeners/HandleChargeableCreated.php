<?php

namespace CorvMC\Finance\Listeners;

use App\Enums\CreditType;
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

            // Update derived fields on reservation (cost, free_hours_used)
            $this->updateDerivedFields($chargeable, $pricing);

            // Deduct credits if not deferred
            if (! $deferCredits && ! empty($pricing->credits_applied)) {
                $this->deductCredits($user, $pricing->credits_applied, $chargeable);
            }
        });
    }

    /**
     * Update derived fields on chargeable (free_hours_used) if supported.
     *
     * @param  Chargeable&Model  $chargeable
     * @param  \CorvMC\Finance\Data\PriceCalculationData  $pricing
     */
    protected function updateDerivedFields($chargeable, $pricing): void
    {
        // Calculate free hours from credits applied if the model supports it
        if ($chargeable->isFillable('free_hours_used')) {
            $freeHoursBlocks = $pricing->credits_applied['free_hours'] ?? 0;
            $minutesPerBlock = config('finance.credits.minutes_per_block', 30);
            $chargeable->updateQuietly([
                'free_hours_used' => ($freeHoursBlocks * $minutesPerBlock) / 60,
            ]);
        }
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
