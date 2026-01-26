<?php

namespace CorvMC\Finance\Actions\Pricing;

use CorvMC\Finance\Enums\CreditType;
use App\Models\User;
use CorvMC\Finance\Contracts\Chargeable;
use CorvMC\Finance\Data\PriceCalculationData;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Calculate price for a chargeable entity with credit application.
 *
 * Uses config-driven pricing rates and applies user credits
 * (e.g., free practice hours for sustaining members).
 */
class CalculatePriceForUser
{
    use AsAction;

    /**
     * Calculate price breakdown for a chargeable.
     *
     * @param  Chargeable  $chargeable  The item to price
     * @param  User|null  $user  Override user (defaults to chargeable's billable user)
     */
    public function handle(Chargeable $chargeable, ?User $user = null): PriceCalculationData
    {
        $user = $user ?? $chargeable->getBillableUser();
        $chargeableClass = get_class($chargeable);

        // Get pricing config for this chargeable type
        $pricingConfig = config("finance.pricing.{$chargeableClass}");

        if (! $pricingConfig) {
            throw new \RuntimeException("No pricing configuration found for {$chargeableClass}");
        }

        $rate = $pricingConfig['rate']; // cents per unit
        $unit = $pricingConfig['unit'];
        $billableUnits = $chargeable->getBillableUnits();

        // Calculate gross amount (before credits)
        $amount = (int) round($rate * $billableUnits);

        // Determine if user qualifies for credits
        $creditsEligible = $user->isSustainingMember();
        $creditsApplied = [];
        $netAmount = $amount;

        if ($creditsEligible) {
            // Get applicable credit type for this chargeable
            $creditTypeKey = config("finance.credits.applicable.{$chargeableClass}");

            if ($creditTypeKey) {
                $creditType = CreditType::from($creditTypeKey);
                $creditValuePerBlock = config("finance.credits.value.{$creditTypeKey}", 0);
                $minutesPerBlock = config('finance.credits.minutes_per_block', 30);

                // Get user's current credit balance in blocks
                $availableBlocks = $user->getCreditBalance($creditType);

                if ($availableBlocks > 0 && $creditValuePerBlock > 0) {
                    // Calculate how many blocks are needed to cover the amount
                    $blocksNeeded = (int) ceil($amount / $creditValuePerBlock);

                    // Apply available blocks (up to what's needed)
                    $blocksToApply = min($availableBlocks, $blocksNeeded);

                    // Calculate credit value being applied
                    $creditValue = $blocksToApply * $creditValuePerBlock;

                    // Cap credit value at the gross amount
                    $creditValue = min($creditValue, $amount);

                    $creditsApplied[$creditType->value] = $blocksToApply;
                    $netAmount = max(0, $amount - $creditValue);
                }
            }
        }

        return new PriceCalculationData(
            amount: $amount,
            credits_applied: $creditsApplied,
            net_amount: $netAmount,
            rate: $rate,
            unit: $unit,
            billable_units: $billableUnits,
            credits_eligible: $creditsEligible,
        );
    }

    /**
     * Calculate without applying credits.
     *
     * Useful for displaying full price before member benefits.
     */
    public function withoutCredits(Chargeable $chargeable): PriceCalculationData
    {
        $chargeableClass = get_class($chargeable);
        $pricingConfig = config("finance.pricing.{$chargeableClass}");

        if (! $pricingConfig) {
            throw new \RuntimeException("No pricing configuration found for {$chargeableClass}");
        }

        $rate = $pricingConfig['rate'];
        $unit = $pricingConfig['unit'];
        $billableUnits = $chargeable->getBillableUnits();
        $amount = (int) round($rate * $billableUnits);

        return new PriceCalculationData(
            amount: $amount,
            credits_applied: [],
            net_amount: $amount,
            rate: $rate,
            unit: $unit,
            billable_units: $billableUnits,
            credits_eligible: false,
        );
    }
}
