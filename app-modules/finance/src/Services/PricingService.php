<?php

namespace CorvMC\Finance\Services;

use App\Models\User;
use CorvMC\Finance\Contracts\Chargeable;
use CorvMC\Finance\Data\PriceCalculationData;
use CorvMC\Finance\Enums\CreditType;

/**
 * Service for calculating prices and applying credits.
 *
 * This service handles price calculations for chargeable entities,
 * including the application of user credits and member benefits.
 */
class PricingService
{
    /**
     * Calculate price breakdown for a chargeable entity with credit application.
     *
     * Uses config-driven pricing rates and applies user credits
     * (e.g., free practice hours for sustaining members).
     *
     * @param Chargeable $chargeable The item to price
     * @param User|null $user Override user (defaults to chargeable's billable user)
     * @return PriceCalculationData The price calculation breakdown
     * @throws \RuntimeException If no pricing configuration is found
     */
    public function calculatePriceForUser(Chargeable $chargeable, ?User $user = null): PriceCalculationData
    {
        $user = $user ?? $chargeable->getBillableUser();
        $chargeableClass = get_class($chargeable);

        // Get pricing config for this chargeable type
        $pricingConfig = config("finance.pricing.{$chargeableClass}");

        if (!$pricingConfig) {
            throw new \RuntimeException("No pricing configuration found for {$chargeableClass}");
        }

        $billableUnits = $chargeable->getBillableUnits();

        // Determine if user qualifies for credits
        $creditsToApply = [];

        // Get applicable credit type for this chargeable
        $creditTypeKey = config("finance.credits.applicable.{$chargeableClass}");

        if ($creditTypeKey) {
            $creditType = CreditType::from($creditTypeKey);

            // Get user's current credit balance
            $availableCredits = $user->getCreditBalance($creditType);

            // Apply as many credits as possible, up to the billable units
            $blocksToApply = min($availableCredits, $billableUnits);

            // Calculate billable units after applying credits
            $creditsToApply[$creditType->value] = $blocksToApply;
        }

        $totalCreditsApplied = array_sum($creditsToApply);
        $effectiveBillableUnits = max(0, $billableUnits - $totalCreditsApplied);


        $rate = $pricingConfig['rate']; // cents per unit
        $unit = $pricingConfig['unit'];

        // Calculate gross amount (before credits)
        $amount = (int) round($rate * $billableUnits);
        $netAmount = (int) round($rate * $effectiveBillableUnits);

        return new PriceCalculationData(
            amount: $amount,
            credits_applied: $creditsToApply,
            net_amount: $netAmount,
            rate: $rate,
            unit: $unit,
            billable_units: $billableUnits,
            credits_eligible: true
        );
    }

    /**
     * Calculate price without applying credits.
     *
     * Useful for displaying full price before member benefits.
     *
     * @param Chargeable $chargeable The item to price
     * @return PriceCalculationData The price calculation without credits
     * @throws \RuntimeException If no pricing configuration is found
     */
    public function calculateWithoutCredits(Chargeable $chargeable): PriceCalculationData
    {
        $chargeableClass = get_class($chargeable);
        $pricingConfig = config("finance.pricing.{$chargeableClass}");

        if (!$pricingConfig) {
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
