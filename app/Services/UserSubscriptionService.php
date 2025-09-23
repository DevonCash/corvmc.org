<?php

namespace App\Services;

use App\Models\User;
use App\Facades\PaymentService;
use App\Facades\MemberBenefitsService;
use App\Data\Subscription\SubscriptionStatsData;
use App\Exceptions\SubscriptionPriceNotFoundException;
use App\Exceptions\SubscriptionNotFoundException;
use Brick\Money\Money;
use Carbon\Carbon;
use Error;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Checkout;
use Laravel\Cashier\Subscription;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;

class UserSubscriptionService
{
    /**
     * Get all sustaining members (role-based only).
     */
    public function getSustainingMembers(): Collection
    {
        return Cache::remember('sustaining_members', 1800, function () {
            return User::whereHas('roles', function ($query) {
                $query->where('name', config('membership.member_role', 'sustaining member'));
            })->with(['profile', 'subscriptions'])->get();
        });
    }

    /**
     * Get subscription statistics.
     */
    public function getSubscriptionStats(): SubscriptionStatsData
    {
        return Cache::remember('subscription_stats', 1800, function () {
            $totalUsers = User::count();
            $sustainingMembers = $this->getSustainingMembers()->count();

            // Calculate total allocated hours based on actual subscription amounts
            $totalAllocatedHours = $this->getSustainingMembers()
                ->sum(fn($user) => MemberBenefitsService::getUserMonthlyFreeHours($user));

            return new SubscriptionStatsData(
                total_users: $totalUsers,
                sustaining_members: $sustainingMembers,
                total_free_hours_allocated: $totalAllocatedHours,
            );
        });
    }

    /**
     * Create a Stripe subscription with sliding scale pricing.
     *
     * @throws SubscriptionPriceNotFoundException
     * @throws ApiErrorException
     */
    public function createSubscription(User $user, Money $baseAmount, bool $coverFees = false): Checkout
    {
        // Get base price ID
        $basePrice = $this->getBasePrice($baseAmount);

        // Build subscription prices array
        $subscriptionPrices = [$basePrice->id];

        if ($coverFees) {
            $feeCoveragePriceId = $this->getFeeCoverage($basePrice->id);
            $subscriptionPrices[] = $feeCoveragePriceId;
        }

        // Use Cashier's multi-product subscription support
        return $user->newSubscription('default', $subscriptionPrices)
            ->checkout([
                'success_url' => route('subscriptions.checkout.success') . '?user_id=' . $user->id . '&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('subscriptions.checkout.cancel') . '?user_id=' . $user->id,
            ]);
    }

    /**
     * Update an existing Stripe subscription amount.
     *
     * @throws SubscriptionPriceNotFoundException
     * @throws ApiErrorException
     */
    public function updateSubscriptionAmount(User $user, Money $baseAmount, bool $coverFees = false): ?Checkout
    {
        // Get required price IDs
        $basePrice = $this->getBasePrice($baseAmount);

        // Build new subscription prices array
        $newPrices = [$basePrice];

        if ($coverFees) {
            $feeCoveragePriceId = $this->getFeeCoverage($basePrice->id);

            $newPrices[] = $feeCoveragePriceId;
        }

        // Get user's active membership subscription
        $subscription = $user->subscriptions()
            ->where('stripe_status', 'active')
            ->first();

        if (!$subscription) {
            // No active subscription, create new one
            return $this->createSubscription($user, $baseAmount, $coverFees);
        }

        $newTotal = $baseAmount;
        if ($coverFees) {
            $newTotal = $newTotal->plus(PaymentService::calculateFees($baseAmount));
        }
        $billingPeriodPeak = $this->getBillingPeriodPeakAmount($subscription);

        $breakdown = PaymentService::getFeeBreakdown($baseAmount, $coverFees);

        if ($newTotal->isGreaterThan($billingPeriodPeak)) {
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
     * Check if user has an active membership subscription.
     */
    public function hasActiveSubscription(User $user): bool
    {
        return $user->subscribed('default');
    }

    /**
     * Get user's active membership subscription.
     */
    public function getActiveSubscription(User $user): ?Subscription
    {
        $subscription = $user->subscription('default');
        
        return $subscription && $subscription->active() ? $subscription : null;
    }


    /**
     * Cancel a user's subscription at period end.
     *
     * @throws SubscriptionNotFoundException
     */
    public function cancelSubscription(User $user): Carbon
    {
        $subscription = $user->subscription('default');
        
        if (!$subscription || !$subscription->active()) {
            throw new SubscriptionNotFoundException('No active membership subscription found');
        }

        $subscription->cancel();
        
        return $subscription->ends_at;
    }

    /**
     * Resume a cancelled subscription.
     *
     * @throws SubscriptionNotFoundException
     */
    public function resumeSubscription(User $user): void
    {
        $subscription = $user->subscription('default');
        
        if (!$subscription || !$subscription->cancelled()) {
            throw new SubscriptionNotFoundException('No cancelled subscription found to resume');
        }

        $subscription->resume();
    }


    // TODO: Do we need this??
    /**
     * Update user membership status based on current Stripe subscriptions only.
     */
    public function updateUserMembershipStatus(User $user): void
    {
        // Check if user has active subscription above threshold
        $shouldBeSustainingMember = !!$this->getActiveSubscription($user);
        
        // Update role accordingly
        if ($shouldBeSustainingMember && !$user->isSustainingMember()) {
            $user->makeSustainingMember();
            \Log::info('Assigned sustaining member role via Stripe subscription', ['user_id' => $user->id]);
        } elseif (!$shouldBeSustainingMember && $user->isSustainingMember()) {
            $user->removeSustainingMember();
            \Log::info('Removed sustaining member role - no qualifying Stripe subscription', ['user_id' => $user->id]);
        }

        // Clear cached membership status
        Cache::forget("user.{$user->id}.is_sustaining");
    }

    /**
     * Get Stripe price ID for base amount without fee coverage.
     */
    private function getBasePrice(Money $amount): Price
    {
        $basePrices = Cache::remember('stripe_membership_base_prices', 3600, function () {
            return collect(Cashier::stripe()->prices->all(['product' => config('services.stripe.membership_product_id'), 'active' => true])->data);
        });

        $price = $basePrices->find(fn($price) => $price->unit_amount === $amount->getMinorAmount()->toInt());
        if (!$price) {
            throw new SubscriptionPriceNotFoundException($amount->getMinorAmount()->toInt(), false);
        }
        return $price;
    }

    /**
     * Get Stripe price ID for fee coverage amount.
     */
    private function getFeeCoverage(string $forProductId): Price
    {
        $coveragePrices = Cache::remember('stripe_fee_coverage_' . $forProductId, 3600, function () use ($forProductId) {
            return collect(Cashier::stripe()->prices->all([
                'active' => true,
                'product' => config('services.stripe.fee_coverage_product_id'),
                'lookup_key' => ['fee_' . $forProductId]
            ])->data);
        });

        if ($coveragePrices->isEmpty()) {
            throw new Error('No fee coverage price found for product: ' . $forProductId, true);
        }
        return $coveragePrices->first();
    }

    /**
     * Calculate the current subscription total amount.
     *
     * @throws ApiErrorException
     */
    private function getCurrentSubscriptionTotal($subscription): float
    {
        $stripeSubscription = Cashier::stripe()->subscriptions->retrieve($subscription->stripe_id);

        $total = 0;
        foreach ($stripeSubscription->items->data as $item) {
            $total += $item->price->unit_amount * $item->quantity;
        }

        return $total / 100; // Convert from cents to dollars
    }

    /**
     * Calculate the new subscription total amount.
     */
    private function calculateNewSubscriptionTotal(Money $baseAmount, bool $coverFees): float
    {
        if ($coverFees) {
            $totalWithCoverage = PaymentService::calculateTotalWithFeeCoverage($baseAmount);
            return $totalWithCoverage->getAmount()->toFloat();
        }

        return $baseAmount->getAmount()->toFloat();
    }

    /**
     * Get the highest amount charged for this subscription in the current billing period.
     */
    private function getBillingPeriodPeakAmount($subscription): float
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
            ->filter(fn($invoice) => in_array($invoice->status, ['paid', 'open']))
            ->flatMap(fn($invoice) => $invoice->lines->data)
            ->filter(fn($line) => $line->subscription === $subscription->stripe_id)
            ->map(fn($line) => $line->amount)
            ->sum() / 100;
    }
}
