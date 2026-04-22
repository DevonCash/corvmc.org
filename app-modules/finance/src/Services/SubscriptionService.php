<?php

namespace CorvMC\Finance\Services;

use App\Models\User;
use Brick\Money\Money;
use CorvMC\Finance\Data\SubscriptionStatsData;
use CorvMC\Finance\Exceptions\SubscriptionPriceNotFoundException;
use CorvMC\Finance\Models\Subscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Checkout;
use Stripe\Price;

/**
 * Service for managing subscriptions and membership status.
 * 
 * This service handles subscription creation, updates, cancellation,
 * membership status synchronization, and subscription statistics.
 */
class SubscriptionService
{
    /**
     * Create a Stripe subscription with sliding scale pricing.
     *
     * @param User $user The user to create subscription for
     * @param Money $baseAmount The base subscription amount
     * @param bool $coverFees Whether to cover processing fees
     * @return Checkout The checkout session
     * @throws SubscriptionPriceNotFoundException
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function createSubscription(User $user, Money $baseAmount, bool $coverFees = false): Checkout
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
                'metadata' => [
                    'user_id' => $user->id,
                    'type' => 'sliding_scale_membership',
                    'base_amount' => $baseAmount->getMinorAmount()->toInt(),
                    'covers_fees' => $coverFees ? 'true' : 'false',
                ],
            ]);
    }

    /**
     * Cancel a user's subscription at period end.
     * 
     * @param User $user The user whose subscription to cancel
     * @return \Carbon\Carbon The date when subscription ends
     * @throws \CorvMC\Finance\Exceptions\SubscriptionNotFoundException
     */
    public function cancelSubscription(User $user): \Carbon\Carbon
    {
        $subscription = $user->subscription('default');

        if (!$subscription || !$subscription->active()) {
            throw new \CorvMC\Finance\Exceptions\SubscriptionNotFoundException('No active membership subscription found');
        }

        $subscription->cancel();

        return $subscription->ends_at;
    }

    /**
     * Resume a cancelled subscription.
     * 
     * @param User $user The user whose subscription to resume
     * @throws \CorvMC\Finance\Exceptions\SubscriptionNotFoundException
     */
    public function resumeSubscription(User $user): void
    {
        $subscription = $user->subscription('default');

        if (!$subscription || !$subscription->canceled()) {
            throw new \CorvMC\Finance\Exceptions\SubscriptionNotFoundException('No cancelled subscription found to resume');
        }

        $subscription->resume();
    }

    /**
     * Update an existing Stripe subscription amount.
     * 
     * @param User $user The user to update
     * @param Money $baseAmount The new subscription amount
     * @param bool $coverFees Whether to cover processing fees
     * @return Checkout|null The checkout session or null if swap was handled directly
     * @throws \CorvMC\Finance\Exceptions\SubscriptionPriceNotFoundException
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function updateSubscriptionAmount(User $user, Money $baseAmount, bool $coverFees = false): ?Checkout
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
        /** @var \Laravel\Cashier\Subscription|null $subscription */
        $subscription = $user->subscriptions()
            ->where('stripe_status', 'active')
            ->first();

        if (!$subscription) {
            // No active subscription, create new one
            return $this->createSubscription($user, $baseAmount, $coverFees);
        }

        $newTotal = $baseAmount;
        if ($coverFees) {
            $newTotal = $newTotal->plus(app(FeeService::class)->calculateProcessingFee($baseAmount));
        }
        $billingPeriodPeak = $this->getBillingPeriodPeakAmount($subscription);

        $breakdown = app(FeeService::class)->getFeeBreakdown($baseAmount, $coverFees);

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
     * Update user membership status based on Stripe subscription state.
     *
     * This checks both our DB (fast) and Stripe API (authoritative) to prevent
     * removing benefits from users who paid but experienced a sync failure.
     *
     * Priority: Prevent removing benefits from paying customers over accidentally
     * granting benefits to non-paying users.
     * 
     * @param User $user The user to update status for
     */
    public function updateUserMembershipStatus(User $user): void
    {
        // First check our DB (fast)
        $hasSubscriptionInDb = $user->subscription();

        // If DB says they have a subscription, trust it and keep role
        if ($hasSubscriptionInDb?->active()) {
            if (!$user->isSustainingMember()) {
                $user->makeSustainingMember();
                Log::info('Assigned sustaining member role via DB subscription', ['user_id' => $user->id]);
            }
            Cache::forget("user.{$user->id}.is_sustaining");
            return;
        }

        // DB says no subscription, but check Stripe API to be sure
        // This prevents removing benefits if Cashier sync failed
        if ($user->stripe_id) {
            try {
                $stripeSubscriptions = Cashier::stripe()->subscriptions->all([
                    'customer' => $user->stripe_id,
                    'status' => 'active',
                    'limit' => 1,
                ]);

                if ($stripeSubscriptions->data && count($stripeSubscriptions->data) > 0) {
                    // Subscription exists in Stripe but not in our DB - sync issue!
                    Log::warning('Subscription sync mismatch - exists in Stripe but not DB', [
                        'user_id' => $user->id,
                        'stripe_id' => $user->stripe_id,
                        'stripe_subscription_id' => $stripeSubscriptions->data[0]->id,
                    ]);

                    // Keep the role - user has paid
                    if (!$user->isSustainingMember()) {
                        $user->makeSustainingMember();
                        Log::info('Assigned sustaining member role via Stripe API (DB sync failed)', [
                            'user_id' => $user->id,
                        ]);
                    }

                    Cache::forget("user.{$user->id}.is_sustaining");
                    return;
                }
            } catch (\Exception $e) {
                Log::error('Failed to query Stripe API for subscription status', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                // On API error, be conservative - don't remove benefits
                return;
            }
        }

        // Confirmed: No subscription in DB or Stripe - safe to remove role
        if ($user->isSustainingMember()) {
            $user->removeSustainingMember();
            Log::info('Removed sustaining member role - no subscription in DB or Stripe', [
                'user_id' => $user->id,
            ]);
        }

        Cache::forget("user.{$user->id}.is_sustaining");
    }

    /**
     * Get the highest amount charged for a subscription in the current billing period.
     * 
     * @param \Laravel\Cashier\Subscription $subscription The subscription to check
     * @return float The peak amount in dollars
     */
    public function getBillingPeriodPeakAmount(\Laravel\Cashier\Subscription $subscription): float
    {
        $stripeSubscription = Cashier::stripe()->subscriptions->retrieve($subscription->stripe_id);
        $currentPeriodStart = $stripeSubscription->current_period_start;

        // Get all invoices for this subscription since the current period started
        $invoices = collect(Cashier::stripe()->invoices->all([
            'subscription' => $subscription->stripe_id,
            'created' => ['gte' => $currentPeriodStart],
            'limit' => 100,
        ])->data);

        return $invoices
            ->filter(fn ($invoice) => in_array($invoice->status, ['paid', 'open']))
            ->flatMap(fn ($invoice) => $invoice->lines->data)
            ->filter(fn ($line) => $line->subscription === $subscription->stripe_id)
            ->map(fn ($line) => $line->amount)
            ->sum() / 100;
    }

    /**
     * Get all sustaining members (role-based only).
     * 
     * @return \Illuminate\Database\Eloquent\Collection Collection of users with sustaining member role
     */
    public function getSustainingMembers(): \Illuminate\Database\Eloquent\Collection
    {
        return \Illuminate\Support\Facades\Cache::remember('sustaining_members', 1800, function () {
            return User::whereHas('roles', function ($query) {
                $query->where('name', config('membership.member_role', 'sustaining member'));
            })->with(['profile', 'subscriptions'])->get();
        });
    }

    /**
     * Process a successful subscription checkout.
     *
     * Called when we have verified confirmation from Stripe that a subscription payment succeeded.
     * This assigns the sustaining member role immediately and allocates credits.
     *
     * This action trusts that the caller has already verified the payment with Stripe's API.
     * It does NOT check the database for subscription state - that's what UpdateUserMembershipStatus is for.
     *
     * Idempotent - safe to call multiple times for the same user.
     *
     * @param int $userId The user ID from metadata
     * @param string $sessionId The Stripe checkout session ID
     * @param array $metadata Additional metadata from the checkout session
     * @return bool Whether processing was successful
     */
    public function processSubscriptionCheckout(int $userId, string $sessionId, array $metadata = []): bool
    {
        try {
            if (!$userId) {
                Log::warning('No user ID provided for subscription checkout processing', ['session_id' => $sessionId]);
                return false;
            }

            $user = User::find($userId);

            if (!$user) {
                Log::error('User not found for subscription checkout', [
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                ]);
                return false;
            }

            // Assign sustaining member role immediately
            // (idempotent - only assigns if not already assigned)
            if (!$user->isSustainingMember()) {
                $user->makeSustainingMember();
                Log::info('Assigned sustaining member role from verified Stripe payment', [
                    'user_id' => $user->id,
                    'session_id' => $sessionId,
                ]);
            }

            // Allocate monthly credits based on the subscription amount from metadata
            // (idempotent - won't double-allocate in same month)
            // Pass cents (integer) to maintain precision
            $baseAmountInCents = $metadata['base_amount'] ?? null;
            app(MemberBenefitService::class)->allocateUserMonthlyCredits($user, $baseAmountInCents);

            Log::info('Successfully processed subscription checkout', [
                'user_id' => $userId,
                'session_id' => $sessionId,
                'base_amount' => $metadata['base_amount'] ?? null,
                'covers_fees' => $metadata['covers_fees'] ?? null,
                'is_sustaining_member' => $user->isSustainingMember(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error processing subscription checkout', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'session_id' => $sessionId,
                'metadata' => $metadata,
            ]);

            return false;
        }
    }

    /**
     * Get Stripe price for base amount without fee coverage.
     * 
     * @param Money $amount The amount to find price for
     * @return Price The Stripe price object
     * @throws SubscriptionPriceNotFoundException
     */
    protected function getBasePrice(Money $amount): Price
    {
        $basePrices = collect(Cashier::stripe()->prices->all([
            'product' => config('services.stripe.membership_product_id'),
            'active' => true,
            'limit' => 100
        ])->data);

        $price = $basePrices->first(fn ($price) => $price->unit_amount === $amount->getMinorAmount()->toInt());
        if (!$price) {
            throw new SubscriptionPriceNotFoundException($amount->getAmount()->toInt(), false);
        }

        return $price;
    }

    /**
     * Get Stripe price for fee coverage amount.
     * 
     * @param string $forProductId The product ID to get fee coverage for
     * @return Price The fee coverage price
     * @throws \Error
     */
    protected function getFeeCoverage(string $forProductId): Price
    {
        $coveragePrices = Cache::remember('stripe_fee_coverage_'.$forProductId, 3600, function () use ($forProductId) {
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

    /**
     * Check if two sets of prices are equal.
     * 
     * @param string|array $prices1 First set of prices
     * @param array $prices2 Second set of prices
     * @return bool True if prices are equal
     */
    protected function arePricesEqual($prices1, array $prices2): bool
    {
        $prices1 = is_array($prices1) ? $prices1 : [$prices1];
        sort($prices1);
        sort($prices2);
        
        return $prices1 === $prices2;
    }

}