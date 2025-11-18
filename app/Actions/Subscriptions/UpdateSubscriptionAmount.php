<?php

namespace App\Actions\Subscriptions;

use App\Exceptions\SubscriptionPriceNotFoundException;
use App\Models\User;
use Brick\Money\Money;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Checkout;
use Laravel\Cashier\Subscription;
use Lorisleiva\Actions\Concerns\AsAction;
use Stripe\Price;

class UpdateSubscriptionAmount
{
    use AsAction;

    /**
     * Update an existing Stripe subscription amount.
     *
     * @throws SubscriptionPriceNotFoundException
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function handle(User $user, Money $baseAmount, bool $coverFees = false): ?Checkout
    {
        // Get required price IDs
        $basePrice = $this->getBasePrice($baseAmount);

        // Build new subscription prices array
        $newPrices = [$basePrice->id];

        if ($coverFees) {
            $feeCoveragePrice = $this->getFeeCoverage($basePrice->id);
            $newPrices[] = $feeCoveragePrice->id;
        }

        // Get user's active membership subscription
        /** @var Subscription|null $subscription */
        $subscription = $user->subscriptions()
            ->where('stripe_status', 'active')
            ->first();

        if (! $subscription) {
            // No active subscription, create new one
            return CreateSubscription::run($user, $baseAmount, $coverFees);
        }

        $newTotal = $baseAmount;
        if ($coverFees) {
            $newTotal = $newTotal->plus(\App\Actions\Payments\CalculateProcessingFee::run($baseAmount));
        }
        $billingPeriodPeak = GetBillingPeriodPeakAmount::run($subscription);

        $breakdown = \App\Actions\Payments\GetFeeBreakdown::run($baseAmount, $coverFees);

        if ($newTotal->isGreaterThan(Money::ofMinor($billingPeriodPeak * 100, 'USD'))) {
            // True upgrade: New amount exceeds what's been paid this billing period
            $subscription->swapAndInvoice($newPrices);

            return null;
        } else {
            // Downgrade or return to previous amount: No charge since already paid this period
            $subscription->noProrate()->swap($newPrices);

            return null;
        }
    }

    /**
     * Get Stripe price ID for base amount without fee coverage.
     */
    private function getBasePrice(Money $amount): Price
    {
        $basePrices = collect(Cashier::stripe()->prices->all(['product' => config('services.stripe.membership_product_id'), 'active' => true, 'limit' => 100])->data);

        $price = $basePrices->first(fn ($price) => $price->unit_amount === $amount->getMinorAmount()->toInt());
        if (! $price) {
            throw new SubscriptionPriceNotFoundException($amount->getAmount()->toInt(), false);
        }

        return $price;
    }

    /**
     * Get Stripe price ID for fee coverage amount.
     */
    private function getFeeCoverage(string $forProductId): Price
    {
        $coveragePrices = \Illuminate\Support\Facades\Cache::remember('stripe_fee_coverage_'.$forProductId, 3600, function () use ($forProductId) {
            return collect(Cashier::stripe()->prices->all([
                'active' => true,
                'product' => config('services.stripe.fee_coverage_product_id'),
                'lookup_keys' => ['fee_'.$forProductId],
            ])->data);
        });

        if ($coveragePrices->isEmpty()) {
            throw new \Error('No fee coverage price found for product: '.$forProductId, true);
        }

        return $coveragePrices->first();
    }
}
