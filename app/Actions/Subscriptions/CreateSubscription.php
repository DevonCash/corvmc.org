<?php

namespace App\Actions\Subscriptions;

use App\Exceptions\SubscriptionPriceNotFoundException;
use App\Models\User;
use Brick\Money\Money;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Checkout;
use Lorisleiva\Actions\Concerns\AsAction;
use Stripe\Price;

class CreateSubscription
{
    use AsAction;

    /**
     * Create a Stripe subscription with sliding scale pricing.
     *
     * @throws SubscriptionPriceNotFoundException
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function handle(User $user, Money $baseAmount, bool $coverFees = false): Checkout
    {
        // Get base price ID
        $basePrice = $this->getBasePrice($baseAmount);

        // Build subscription prices array
        $subscriptionPrices = [$basePrice->id];

        if ($coverFees) {
            $feeCoveragePrice = $this->getFeeCoverage($basePrice->id);
            $subscriptionPrices[] = $feeCoveragePrice->id;
        }

        // Use Cashier's multi-product subscription support
        return $user->newSubscription('default', $subscriptionPrices)
            ->checkout([
                'success_url' => route('checkout.success').'?user_id='.$user->id.'&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('checkout.cancel').'?user_id='.$user->id.'&type=sliding_scale_membership',
            ]);
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
