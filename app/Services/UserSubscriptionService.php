<?php

namespace App\Services;

use App\Models\User;
use App\Facades\PaymentService;
use App\Services\MemberBenefitsService;
use App\Exceptions\SubscriptionPriceNotFoundException;
use App\Exceptions\SubscriptionNotFoundException;
use App\Exceptions\StripeCustomerNotFoundException;
use Brick\Money\Money;
use Carbon\Carbon;
use Error;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Checkout;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;

class UserSubscriptionService
{
    const SUSTAINING_MEMBER_THRESHOLD = 10.00;

    public function __construct(
        private MemberBenefitsService $memberBenefitsService
    ) {}

    /**
     * Get all sustaining members (role-based only).
     */
    public function getSustainingMembers(): Collection
    {
        return Cache::remember('sustaining_members', 1800, function () {
            return User::whereHas('roles', function ($query) {
                $query->where('name', 'sustaining member');
            })->with(['profile', 'subscriptions'])->get();
        });
    }

    /**
     * Get subscription statistics.
     */
    public function getSubscriptionStats(): array
    {
        return Cache::remember('subscription_stats', 1800, function () {
            $totalUsers = User::count();
            $sustainingMembers = $this->getSustainingMembers()->count();

            // Calculate total allocated hours based on actual subscription amounts
            $totalAllocatedHours = $this->getSustainingMembers()
                ->sum(fn($user) => $this->memberBenefitsService->getUserMonthlyFreeHours($user));

            return [
                'total_users' => $totalUsers,
                'sustaining_members' => $sustainingMembers,
                'sustaining_percentage' => $totalUsers > 0 ? ($sustainingMembers / $totalUsers) * 100 : 0,
                'total_free_hours_allocated' => $totalAllocatedHours,
            ];
        });
    }


    /**
     * Calculate user's free hours usage for a specific month.
     */
    public function getFreeHoursUsageForMonth(User $user, Carbon $month): array
    {
        $reservations = $user->reservations()
            ->whereMonth('reserved_at', $month->month)
            ->whereYear('reserved_at', $month->year)
            ->where('free_hours_used', '>', 0)
            ->get();

        $totalFreeHours = $reservations->sum('free_hours_used');
        $totalHours = $reservations->sum('hours_used');
        $totalPaid = $reservations->sum('cost');

        $allocatedFreeHours = $this->memberBenefitsService->getUserMonthlyFreeHours($user);

        return [
            'month' => $month->format('Y-m'),
            'total_reservations' => $reservations->count(),
            'total_hours' => $totalHours,
            'free_hours_used' => $totalFreeHours,
            'paid_hours' => $totalHours - $totalFreeHours,
            'total_cost' => $totalPaid,
            'allocated_free_hours' => $allocatedFreeHours,
            'unused_free_hours' => max(0, $allocatedFreeHours - $totalFreeHours),
        ];
    }


    /**
     * Revoke sustaining member status (for admin use).
     */
    public function revokeSustainingMemberStatus(User $user): bool
    {
        if ($user->hasRole('sustaining member')) {
            $user->removeRole('sustaining member');
            return true;
        }
        return false;
    }

    /**
     * Grant sustaining member status manually (for admin use).
     */
    public function grantSustainingMemberStatus(User $user): bool
    {
        if (! $user->hasRole('sustaining member')) {
            $user->assignRole('sustaining member');

            return true;
        }

        return false;
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
    public function updateSubscriptionAmount(User $user, Money $baseAmount, bool $coverFees = false): array
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
            return [
                'message' => "Membership upgraded to {$breakdown['description']} with immediate prorated billing",
                'breakdown' => $breakdown,
                'billing_change' => 'immediate',
            ];
        } else {
            // Downgrade or return to previous amount: No charge since already paid this period
            $subscription->noProrate()->swap($newPrices);

            return [
                'message' => "Membership will change to {$breakdown['description']} at the start of your next billing cycle",
                'breakdown' => $breakdown,
                'billing_change' => 'next_cycle',
            ];
        }
    }

    /**
     * Get subscription display information for a user.
     */
    public function getSubscriptionDisplayInfo(User $user): array
    {
        if (!$user->hasStripeId()) {
            return [
                'has_subscription' => false,
                'status' => 'No Stripe customer',
                'amount' => 0,
            ];
        }

        // Get active subscriptions from Stripe directly
        $activeSubscriptions = Cashier::stripe()->subscriptions->all([
            'customer' => $user->stripe_id,
            'status' => 'active',
            'limit' => 10,
        ]);

        // Find membership subscription by checking if it contains our sliding scale membership product
        $membershipProductId = config('services.stripe.membership_product_id');
        $membershipSubscription = null;

        foreach ($activeSubscriptions->data as $subscription) {
            // Check if subscription contains prices from our sliding scale membership product
            foreach ($subscription->items->data as $item) {
                if ($item->price->product === $membershipProductId) {
                    $membershipSubscription = $subscription;
                    break 2; // Break out of both loops
                }
            }
        }

        if (!$membershipSubscription) {
            return [
                'has_subscription' => false,
                'status' => 'No active membership subscription',
                'amount' => 0,
            ];
        }

        // Try to get amount from local subscription record first for precision
        $localSubscription = $user->subscriptions()->where('stripe_id', $membershipSubscription->id)->first();

        if ($localSubscription && $localSubscription->base_amount) {
            $amount = $localSubscription->base_amount;
            $totalAmount = $localSubscription->total_amount;
        } else {
            // Fallback to Stripe data
            $price = $membershipSubscription->items->data[0]->price ?? null;
            $amount = $price ? PaymentService::fromStripeAmount($price->unit_amount) : Money::zero('USD');
            $totalAmount = $amount;
        }

        return [
            'has_subscription' => true,
            'status' => ucfirst($membershipSubscription->status),
            'amount' => $amount->getAmount()->toFloat(),
            'total_amount' => $totalAmount->getAmount()->toFloat(),
            'formatted_amount' => $localSubscription ? $localSubscription->formatted_base_amount : PaymentService::formatMoney($amount),
            'formatted_total_amount' => $localSubscription ? $localSubscription->formatted_total_amount : PaymentService::formatMoney($totalAmount),
            'covers_fees' => $localSubscription->covers_fees ?? false,
            'interval' => $membershipSubscription->items->data[0]->price->recurring->interval ?? 'month',
            'next_billing' => $membershipSubscription->current_period_end,
            'subscription_id' => $membershipSubscription->id,
        ];
    }

    /**
     * Get fee calculation for display purposes.
     */
    public function getFeeCalculation(Money $baseAmount, bool $coverFees = false): array
    {
        return PaymentService::getFeeBreakdown($baseAmount, $coverFees);
    }

    /**
     * Cancel a user's subscription.
     *
     * @throws StripeCustomerNotFoundException
     * @throws SubscriptionNotFoundException
     * @throws ApiErrorException
     */
    public function cancelSubscription(User $user): array
    {
        if (!$user->hasStripeId()) {
            throw new StripeCustomerNotFoundException();
        }

        // Get active subscriptions from Stripe directly
        $activeSubscriptions = Cashier::stripe()->subscriptions->all([
            'customer' => $user->stripe_id,
            'status' => 'active',
            'limit' => 10,
        ]);

        // Find membership subscription by checking if it contains our sliding scale membership product
        $membershipProductId = config('services.stripe.membership_product_id');
        $membershipSubscription = null;

        foreach ($activeSubscriptions->data as $subscription) {
            // Check if subscription contains prices from our sliding scale membership product
            foreach ($subscription->items->data as $item) {
                if ($item->price->product === $membershipProductId) {
                    $membershipSubscription = $subscription;
                    break 2; // Break out of both loops
                }
            }
        }

        if (!$membershipSubscription) {
            throw new SubscriptionNotFoundException('No active membership subscription found');
        }

        // Cancel at period end
        $membershipSubscription->cancel_at_period_end = true;
        $membershipSubscription->save();

        // Update our local subscription record to reflect the cancellation
        $localSubscription = $user->subscriptions()->where('stripe_id', $membershipSubscription->id)->first();
        if ($localSubscription) {
            $localSubscription->update([
                'ends_at' => \Carbon\Carbon::createFromTimestamp($membershipSubscription->current_period_end),
            ]);

            \Log::info('Updated local subscription with cancellation end date', [
                'user_id' => $user->id,
                'subscription_id' => $membershipSubscription->id,
                'ends_at' => $localSubscription->ends_at,
            ]);
        }

        return [
            'message' => 'Subscription cancelled successfully. You will retain access until the end of your current billing period.',
            'subscription_id' => $membershipSubscription->id,
            'ends_at' => \Carbon\Carbon::createFromTimestamp($membershipSubscription->current_period_end),
        ];
    }

    /**
     * Resume a cancelled subscription by unsetting cancel_at_period_end.
     *
     * @throws StripeCustomerNotFoundException
     * @throws SubscriptionNotFoundException
     * @throws ApiErrorException
     */
    public function resumeSubscription(User $user): array
    {
        if (!$user->hasStripeId()) {
            throw new StripeCustomerNotFoundException();
        }

        // Get subscriptions that are set to cancel at period end
        $subscriptions = Cashier::stripe()->subscriptions->all([
            'customer' => $user->stripe_id,
            'status' => 'active',
            'limit' => 10,
        ]);

        // Find membership subscription by checking if it contains our sliding scale membership product
        $membershipProductId = config('services.stripe.membership_product_id');
        $membershipSubscription = null;

        foreach ($subscriptions->data as $subscription) {
            if ($subscription->cancel_at_period_end) {
                // Check if subscription contains prices from our sliding scale membership product
                foreach ($subscription->items->data as $item) {
                    if ($item->price->product === $membershipProductId) {
                        $membershipSubscription = $subscription;
                        break 2; // Break out of both loops
                    }
                }
            }
        }

        if (!$membershipSubscription) {
            throw new SubscriptionNotFoundException('No cancelled subscription found to resume');
        }

        // Unset cancel at period end
        $membershipSubscription->cancel_at_period_end = false;

        // Update our local subscription record to clear the cancellation
        $localSubscription = $user->subscriptions()->where('stripe_id', $membershipSubscription->id)->first();
        $localSubscription?->update([
            'ends_at' => null,
        ]);

        return [
            'message' => 'Subscription resumed successfully.',
            'subscription_id' => $membershipSubscription->id,
        ];
    }

    /**
     * Sync Stripe subscription data with local Cashier subscription.
     */
    public function syncStripeSubscription(User $user, array $stripeSubscription): void
    {
        // Ensure user has Stripe customer ID
        if (!$user->hasStripeId()) {
            $user->update(['stripe_id' => $stripeSubscription['customer']]);
        }

        // Find or create the subscription in Cashier
        $subscription = $user->subscriptions()->where('stripe_id', $stripeSubscription['id'])->first();

        if (!$subscription) {
            // Extract amount information from Stripe subscription
            $unitAmountCents = $stripeSubscription['items']['data'][0]['price']['unit_amount'] ?? 0;

            // Extract metadata to get original amounts
            $metadata = $stripeSubscription['metadata'] ?? [];
            $baseAmount = isset($metadata['base_amount']) ? (float)$metadata['base_amount'] : ($unitAmountCents / 100);
            $coversFeesFlag = ($metadata['covers_fees'] ?? 'false') === 'true';

            // Create new Cashier subscription (amounts will be automatically cast to Money and stored as integers)
            $user->subscriptions()->create([
                'type' => 'default',
                'stripe_id' => $stripeSubscription['id'],
                'stripe_status' => $stripeSubscription['status'],
                'stripe_price' => $stripeSubscription['items']['data'][0]['price']['id'] ?? null,
                'quantity' => $stripeSubscription['items']['data'][0]['quantity'] ?? 1,
                'base_amount' => $baseAmount,
                'total_amount' => $unitAmountCents / 100,
                'currency' => strtoupper($stripeSubscription['items']['data'][0]['price']['currency'] ?? 'USD'),
                'covers_fees' => $coversFeesFlag,
                'metadata' => $metadata,
                'trial_ends_at' => isset($stripeSubscription['trial_end'])
                    ? \Carbon\Carbon::createFromTimestamp($stripeSubscription['trial_end'])
                    : null,
                'ends_at' => null,
            ]);
        } else {
            // Extract updated amount information
            $unitAmountCents = $stripeSubscription['items']['data'][0]['price']['unit_amount'] ?? 0;
            $metadata = $stripeSubscription['metadata'] ?? [];
            $baseAmount = isset($metadata['base_amount']) ? (float)$metadata['base_amount'] : ($unitAmountCents / 100);
            $coversFeesFlag = ($metadata['covers_fees'] ?? 'false') === 'true';

            // Calculate ends_at based on subscription status and cancel_at_period_end
            $endsAt = null;
            if ($stripeSubscription['status'] === 'canceled' && isset($stripeSubscription['ended_at'])) {
                $endsAt = \Carbon\Carbon::createFromTimestamp($stripeSubscription['ended_at']);
            } elseif ($stripeSubscription['cancel_at_period_end'] && isset($stripeSubscription['current_period_end'])) {
                $endsAt = \Carbon\Carbon::createFromTimestamp($stripeSubscription['current_period_end']);
            }

            // Update existing subscription (amounts will be automatically cast to Money and stored as integers)
            $subscription->update([
                'stripe_status' => $stripeSubscription['status'],
                'stripe_price' => $stripeSubscription['items']['data'][0]['price']['id'] ?? $subscription->stripe_price,
                'quantity' => $stripeSubscription['items']['data'][0]['quantity'] ?? $subscription->quantity,
                'base_amount' => $baseAmount,
                'total_amount' => $unitAmountCents / 100,
                'currency' => strtoupper($stripeSubscription['items']['data'][0]['price']['currency'] ?? $subscription->currency ?? 'USD'),
                'covers_fees' => $coversFeesFlag,
                'metadata' => $metadata,
                'trial_ends_at' => isset($stripeSubscription['trial_end'])
                    ? \Carbon\Carbon::createFromTimestamp($stripeSubscription['trial_end'])
                    : $subscription->trial_ends_at,
                'ends_at' => $endsAt,
            ]);
        }

        \Log::info('Synced Stripe subscription with Cashier', [
            'user_id' => $user->id,
            'subscription_id' => $stripeSubscription['id'],
            'status' => $stripeSubscription['status'],
        ]);
    }

    // TODO: Do we need this??
    /**
     * Update user membership status based on current Stripe subscriptions only.
     */
    public function updateUserMembershipStatus(User $user): void
    {
        try {
            $shouldBeSustainingMember = false;

            // Check if user has active Stripe subscription above threshold
            if ($user->hasStripeId()) {
                $displayInfo = $this->getSubscriptionDisplayInfo($user);
                if ($displayInfo['has_subscription'] && $displayInfo['amount'] >= self::SUSTAINING_MEMBER_THRESHOLD) {
                    $shouldBeSustainingMember = true;
                }
            }

            // Update role accordingly
            if ($shouldBeSustainingMember && !$user->hasRole('sustaining member')) {
                $user->assignRole('sustaining member');
                \Log::info('Assigned sustaining member role via Stripe subscription', ['user_id' => $user->id]);
            } elseif (!$shouldBeSustainingMember && $user->hasRole('sustaining member')) {
                $user->removeRole('sustaining member');
                \Log::info('Removed sustaining member role - no qualifying Stripe subscription', ['user_id' => $user->id]);
            }

            // Clear cached membership status
            Cache::forget("user.{$user->id}.is_sustaining");
        } catch (\Exception $e) {
            \Log::error('Error updating user membership status', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
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
     */
    private function getCurrentSubscriptionTotal($subscription): float
    {
        try {
            $stripeSubscription = Cashier::stripe()->subscriptions->retrieve($subscription->stripe_id);

            $total = 0;
            foreach ($stripeSubscription->items->data as $item) {
                $total += $item->price->unit_amount * $item->quantity;
            }

            return $total / 100; // Convert from cents to dollars
        } catch (ApiErrorException $e) {
            \Log::error('Error getting current subscription total', [
                'subscription_id' => $subscription->stripe_id,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
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
