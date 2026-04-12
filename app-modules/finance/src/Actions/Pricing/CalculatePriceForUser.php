<?php

namespace CorvMC\Finance\Actions\Pricing;

use App\Models\User;
use CorvMC\Finance\Contracts\Chargeable;
use CorvMC\Finance\Data\PriceCalculationData;
use CorvMC\Finance\Services\PricingService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use PricingService::calculatePriceForUser() instead
 * This action is maintained for backward compatibility only.
 * New code should use the PricingService directly.
 */
class CalculatePriceForUser
{
    use AsAction;

    /**
     * @deprecated Use PricingService::calculatePriceForUser() instead
     */
    public function handle(Chargeable $chargeable, ?User $user = null): PriceCalculationData
    {
        return app(PricingService::class)->calculatePriceForUser($chargeable, $user);
    }

    /**
     * @deprecated Use PricingService::calculateWithoutCredits() instead
     */
    public function withoutCredits(Chargeable $chargeable): PriceCalculationData
    {
        return app(PricingService::class)->calculateWithoutCredits($chargeable);
    }
}
