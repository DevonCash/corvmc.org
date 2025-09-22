<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Notifications\DonationReceivedNotification;
use App\Facades\PaymentService;
use Brick\Money\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\Cashier;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Exception\ApiErrorException;
use Stripe\Subscription as StripeSubscription;
use Stripe\Customer as StripeCustomer;
use Stripe\PaymentMethod;

class UserSubscriptionService
{
    const SUSTAINING_MEMBER_THRESHOLD = 10.00;

    const FREE_HOURS_PER_MONTH = 4;
    const HOURS_PER_DOLLAR_AMOUNT = 1; // Number of hours granted
    const DOLLAR_AMOUNT_FOR_HOURS = 5; // Per this dollar amount


    /**
     * Calculate free hours based on contribution amount.
     */
    public static function calculateFreeHours(float $contributionAmount): int
    {
        return intval(floor($contributionAmount / self::DOLLAR_AMOUNT_FOR_HOURS) * self::HOURS_PER_DOLLAR_AMOUNT);
    }

    /**
     * Get the user's monthly free hours based on their subscription amount.
     */
    public static function getUserMonthlyFreeHours(User $user): int
    {
        if (!static::isSustainingMember($user)) {
            return 0;
        }

        $displayInfo = static::getSubscriptionDisplayInfo($user);
        if ($displayInfo['has_subscription']) {
            return static::calculateFreeHours($displayInfo['amount']);
        }

        // Fallback to legacy constant for role-based members without subscriptions
        return self::FREE_HOURS_PER_MONTH;
    }

    /**
     * Check if a user qualifies as a sustaining member.
     */
    public static function isSustainingMember(User $user): bool
    {
        return $user->hasRole('sustaining member');
    }

    /**
     * Get used free hours for user in current month.
     */
    public static function getUsedFreeHoursThisMonth(User $user): float
    {
        return Cache::remember("user.{$user->id}.free_hours." . now()->format('Y-m'), 1800, function () use ($user) {
            return $user->reservations()
                ->whereMonth('reserved_at', now()->month)
                ->whereYear('reserved_at', now()->year)
                ->sum('free_hours_used') ?? 0;
        });
    }

    /**
     * Get remaining free hours for sustaining members this month.
     */
    public static function getRemainingFreeHours(User $user): float
    {
        if (!static::isSustainingMember($user)) {
            return 0;
        }

        $allocatedHours = static::getUserMonthlyFreeHours($user);
        $usedHours = static::getUsedFreeHoursThisMonth($user);

        return max(0, $allocatedHours - $usedHours);
    }

    /**
     * Get user's subscription status and details.
     */
    public function getSubscriptionStatus(User $user): array
    {
        $isSustaining = static::isSustainingMember($user);
        $recentTransaction = static::getMostRecentRecurringTransaction($user);

        return [
            'is_sustaining_member' => $isSustaining,
            'free_hours_per_month' => static::getUserMonthlyFreeHours($user),
            'current_month_used_hours' => $user->getUsedFreeHoursThisMonth(),
            'remaining_free_hours' => $user->getRemainingFreeHours(),
            'last_transaction' => $recentTransaction,
            'subscription_amount' => $recentTransaction?->amount ?? 0,
            'next_billing_estimate' => $recentTransaction
                ? $recentTransaction->created_at->addMonth()
                : null,
        ];
    }

    /**
     * Process a new transaction and update user status if needed.
     */
    public static function processTransaction(Transaction $transaction): bool
    {
        $user = $transaction->user;
        if (! $user) {
            return false;
        }

        // If this is a recurring transaction over the threshold,
        // potentially assign sustaining member role
        if (
            $transaction->type === 'recurring' &&
            $transaction->amount->getAmount()->toFloat() > self::SUSTAINING_MEMBER_THRESHOLD
        ) {

            if (! $user->hasRole('sustaining member')) {
                $user->assignRole('sustaining member');
            }
        }

        // Send donation thank you notification
        $user->notify(new DonationReceivedNotification($transaction));

        return true;
    }

    /**
     * Get all sustaining members.
     */
    public static function getSustainingMembers(): Collection
    {
        return Cache::remember('sustaining_members', 1800, function () {
            $roleBasedMembers = User::whereHas('roles', function ($query) {
                $query->where('name', 'sustaining member');
            })->with(['profile', 'transactions'])->get();

            $transactionBasedMembers = User::whereHas('transactions', function ($query) {
                $query->where('type', 'recurring')
                    ->where('created_at', '>=', now()->subMonth());
            })->with(['profile', 'transactions'])->get()
            ->filter(function ($user) {
                return $user->transactions()
                    ->where('type', 'recurring')
                    ->where('created_at', '>=', now()->subMonth())
                    ->get()
                    ->filter(function ($transaction) {
                        return $transaction->amount->isGreaterThan(Money::of(self::SUSTAINING_MEMBER_THRESHOLD, 'USD'));
                    })
                    ->isNotEmpty();
            });

            return $roleBasedMembers->merge($transactionBasedMembers)->unique('id');
        });
    }

    /**
     * Get subscription statistics.
     */
    public static function getSubscriptionStats(): array
    {
        return Cache::remember('subscription_stats', 1800, function () {
            $totalUsers = User::count();
            $sustainingMembers = static::getSustainingMembers()->count();

            $recentTransactions = Transaction::where('type', 'recurring')
                ->where('created_at', '>=', now()->subMonth())
                ->get();

            $totalMonthlyRevenue = $recentTransactions->reduce(fn($carry, $item) => $carry + $item->amount->getMinorAmount()->toInt(), 0);

            $averageSubscription = $sustainingMembers > 0
                ? $totalMonthlyRevenue / $sustainingMembers / 100
                : 0;

            // Calculate total allocated hours based on actual subscription amounts
            $totalAllocatedHours = static::getSustainingMembers()
                ->sum(fn($user) => static::getUserMonthlyFreeHours($user));

            return [
                'total_users' => $totalUsers,
                'sustaining_members' => $sustainingMembers,
                'sustaining_percentage' => $totalUsers > 0 ? ($sustainingMembers / $totalUsers) * 100 : 0,
                'monthly_revenue' => $totalMonthlyRevenue,
                'average_subscription' => $averageSubscription,
                'total_free_hours_allocated' => $totalAllocatedHours,
            ];
        });
    }

    /**
     * Get users whose subscription might be expiring.
     */
    public function getExpiringSubscriptions(int $daysAhead = 7): Collection
    {
        $cutoffDate = now()->subMonth()->addDays($daysAhead);

        return User::whereHas('transactions', function ($query) use ($cutoffDate) {
            $query->where('type', 'recurring')
                ->where('created_at', '<=', $cutoffDate);
        })->whereDoesntHave('transactions', function ($query) {
            $query->where('type', 'recurring')
                ->where('created_at', '>=', now()->subMonth());
        })->with(['profile', 'transactions'])->get()
        ->filter(function ($user) use ($cutoffDate) {
            // Check if user had qualifying transactions in the expiring period
            $hadQualifying = $user->transactions()
                ->where('type', 'recurring')
                ->where('created_at', '<=', $cutoffDate)
                ->get()
                ->filter(function ($transaction) {
                    return $transaction->amount->isGreaterThan(Money::of(self::SUSTAINING_MEMBER_THRESHOLD, 'USD'));
                })
                ->isNotEmpty();

            // Check if user has recent qualifying transactions
            $hasRecent = $user->transactions()
                ->where('type', 'recurring')
                ->where('created_at', '>=', now()->subMonth())
                ->get()
                ->filter(function ($transaction) {
                    return $transaction->amount->isGreaterThan(Money::of(self::SUSTAINING_MEMBER_THRESHOLD, 'USD'));
                })
                ->isNotEmpty();

            return $hadQualifying && !$hasRecent;
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

        $allocatedFreeHours = static::getUserMonthlyFreeHours($user);

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
     * Get the most recent recurring transaction for a user.
     */
    protected static function getMostRecentRecurringTransaction(User $user): ?Transaction
    {
        return $user->transactions()
            ->where('type', 'recurring')
            ->orderBy('created_at', 'desc')
            ->first();
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
     * Upgrade user to sustaining member based on transaction.
     */
    public static function upgradeToSustainingMember(User $user, Transaction $transaction): bool
    {
        if (!$user->hasRole('sustaining member')) {
            $user->assignRole('sustaining member');

            // Log the upgrade
            \Log::info('User upgraded to sustaining member via webhook', [
                'user_id' => $user->id,
                'transaction_id' => $transaction->transaction_id,
                'amount' => $transaction->amount,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Update user's contribution tracking.
     */
    public function updateContributionTracking(User $user, Transaction $transaction): void
    {
        // This could track total contributions, recent activity, etc.
        // For now, we'll just ensure the transaction is linked to the user
        \Log::info('Contribution tracking updated', [
            'user_id' => $user->id,
            'transaction_id' => $transaction->transaction_id,
            'amount' => $transaction->amount,
            'total_contributions' => $user->transactions()->sum('amount'),
        ]);
    }

    /**
     * Create a Stripe subscription with sliding scale pricing.
     */
    public function createSubscription(User $user, Money $baseAmount, bool $coverFees = false): array
    {
        try {
            // Ensure user has Stripe customer ID
            if (!$user->hasStripeId()) {
                $user->createAsStripeCustomer();
            }

            $breakdown = PaymentService::getFeeBreakdown($baseAmount, $coverFees);
            $totalAmount = Money::of($breakdown['total_amount'], 'USD');

            $metadata = [
                'type' => 'sliding_scale_membership',
                'user_id' => $user->id,
                'base_amount' => $baseAmount->getAmount()->toFloat(),
                'covers_fees' => $coverFees ? 'true' : 'false',
            ];

            $sessionData = [
                'payment_method_types' => ['card'],
                'mode' => 'subscription',
                'customer' => $user->stripe_id,
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => PaymentService::toStripeAmount($totalAmount),
                        'recurring' => ['interval' => 'month'],
                        'product_data' => [
                            'name' => 'Sliding Scale Membership',
                            'description' => $breakdown['description'],
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'subscription_data' => [
                    'metadata' => $metadata,
                ],
                'success_url' => route('subscriptions.checkout.success') . '?user_id=' . $user->id . '&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('subscriptions.checkout.cancel') . '?user_id=' . $user->id,
                'metadata' => $metadata,
            ];

            $session = Cashier::stripe()->checkout->sessions->create($sessionData);

            return [
                'success' => true,
                'checkout_url' => $session->url,
                'session_id' => $session->id,
                'breakdown' => $breakdown,
            ];
        } catch (ApiErrorException $e) {
            \Log::error('Stripe API error creating subscription session', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'base_amount' => $baseAmount->getAmount()->toFloat(),
            ]);

            return [
                'success' => false,
                'error' => 'Payment service error: ' . $e->getMessage(),
            ];
        } catch (\Exception $e) {
            \Log::error('General error creating subscription session', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'base_amount' => $baseAmount->getAmount()->toFloat(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Handle successful subscription checkout session.
     * 
     * This is called from the redirect flow and serves primarily as user feedback.
     * The actual subscription processing happens via webhooks for reliability.
     */
    public function handleSuccessfulSubscription(User $user, string $sessionId): array
    {
        try {
            $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId);

            if ($session->mode !== 'subscription' || !$session->subscription) {
                throw new \Exception('Invalid session for subscription');
            }

            $subscriptionId = $session->subscription;

            // Check if webhook has already processed this subscription
            $existingSubscription = $user->subscriptions()->where('stripe_id', $subscriptionId)->first();

            if ($existingSubscription) {
                // Webhook already processed this - just return success
                \Log::info('Subscription redirect processed - webhook already handled', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscriptionId,
                    'session_id' => $sessionId,
                ]);

                return [
                    'success' => true,
                    'subscription_id' => $subscriptionId,
                    'processed_by_webhook' => true,
                ];
            }

            // Webhook hasn't processed yet - trigger manual processing
            // This serves as a fallback in case webhooks are delayed
            $subscription = Cashier::stripe()->subscriptions->retrieve($subscriptionId);
            $this->syncStripeSubscription($user, $subscription->toArray());
            $this->updateUserMembershipStatus($user);

            \Log::info('Subscription processed via redirect (webhook backup)', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
                'session_id' => $sessionId,
            ]);

            return [
                'success' => true,
                'subscription_id' => $subscriptionId,
                'processed_by_redirect' => true,
            ];

        } catch (\Exception $e) {
            \Log::error('Error handling successful subscription redirect', [
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update an existing Stripe subscription amount by creating a new subscription.
     */
    public function updateSubscriptionAmount(User $user, Money $baseAmount, bool $coverFees = false): array
    {
        try {
            // Get user's current Stripe subscriptions
            $stripeCustomer = Cashier::stripe()->customers->retrieve($user->stripe_id);
            $activeSubscriptions = Cashier::stripe()->subscriptions->all([
                'customer' => $user->stripe_id,
                'status' => 'active',
                'limit' => 10,
            ]);

            // Cancel current subscription(s) related to membership
            foreach ($activeSubscriptions->data as $subscription) {
                if (isset($subscription->metadata['type']) &&
                    $subscription->metadata['type'] === 'sliding_scale_membership') {
                    $subscription->cancel_at_period_end = true;
                    $subscription->save();
                }
            }

            // Create new subscription with updated amount
            return $this->createSubscription($user, $baseAmount, $coverFees);

        } catch (ApiErrorException $e) {
            \Log::error('Stripe API error updating subscription', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'base_amount' => $baseAmount->getAmount()->toFloat(),
            ]);

            return [
                'success' => false,
                'error' => 'Payment service error: ' . $e->getMessage(),
            ];
        } catch (\Exception $e) {
            \Log::error('General error updating subscription', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'base_amount' => $baseAmount->getAmount()->toFloat(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get subscription display information for a user.
     */
    public static function getSubscriptionDisplayInfo(User $user): array
    {
        try {
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

            // Find membership subscription
            $membershipSubscription = null;
            foreach ($activeSubscriptions->data as $subscription) {
                if (isset($subscription->metadata['type']) &&
                    $subscription->metadata['type'] === 'sliding_scale_membership') {
                    $membershipSubscription = $subscription;
                    break;
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
        } catch (ApiErrorException $e) {
            \Log::error('Error retrieving subscription info', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'has_subscription' => false,
                'status' => 'Error retrieving subscription',
                'amount' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get fee calculation for display purposes.
     */
    public function getFeeCalculation(Money $baseAmount, bool $coverFees = false): array
    {
        return PaymentService::getFeeBreakdown($baseAmount, $coverFees);
    }

    /**
     * Get suggested membership amounts.
     */
    public function getSuggestedAmounts(): array
    {
        return [
            10 => ['label' => '$10/month - Basic Support', 'description' => 'Essential community support'],
            25 => ['label' => '$25/month - Enhanced Support', 'description' => 'Recommended contribution level'],
            50 => ['label' => '$50/month - Premium Support', 'description' => 'Strong community investment'],
        ];
    }

    /**
     * Determine current subscription tier based on amount.
     */
    public function getCurrentTier(User $user): ?string
    {
        $displayInfo = static::getSubscriptionDisplayInfo($user);

        if (!$displayInfo['has_subscription']) {
            return null;
        }

        $amount = $displayInfo['amount'];

        return match (true) {
            $amount >= 45 && $amount <= 55 => 'suggested_50',
            $amount >= 20 && $amount <= 30 => 'suggested_25',
            $amount >= 8 && $amount <= 12 => 'suggested_10',
            default => 'custom'
        };
    }

    /**
     * Cancel a user's subscription.
     */
    public function cancelSubscription(User $user): array
    {
        try {
            if (!$user->hasStripeId()) {
                return [
                    'success' => false,
                    'error' => 'No Stripe customer found',
                ];
            }

            // Get active subscriptions from Stripe directly
            $activeSubscriptions = Cashier::stripe()->subscriptions->all([
                'customer' => $user->stripe_id,
                'status' => 'active',
                'limit' => 10,
            ]);

            $membershipSubscription = null;
            foreach ($activeSubscriptions->data as $subscription) {
                if (isset($subscription->metadata['type']) &&
                    $subscription->metadata['type'] === 'sliding_scale_membership') {
                    $membershipSubscription = $subscription;
                    break;
                }
            }

            if (!$membershipSubscription) {
                return [
                    'success' => false,
                    'error' => 'No active membership subscription found',
                ];
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
                'success' => true,
                'message' => 'Subscription cancelled successfully. You will retain access until the end of your current billing period.',
                'subscription_id' => $membershipSubscription->id,
                'ends_at' => \Carbon\Carbon::createFromTimestamp($membershipSubscription->current_period_end),
            ];
        } catch (ApiErrorException $e) {
            \Log::error('Stripe API error cancelling subscription', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Payment service error: ' . $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Resume a cancelled subscription by unsetting cancel_at_period_end.
     */
    public function resumeSubscription(User $user): array
    {
        try {
            if (!$user->hasStripeId()) {
                return [
                    'success' => false,
                    'error' => 'No Stripe customer found',
                ];
            }

            // Get subscriptions that are set to cancel at period end
            $subscriptions = Cashier::stripe()->subscriptions->all([
                'customer' => $user->stripe_id,
                'status' => 'active',
                'limit' => 10,
            ]);

            $membershipSubscription = null;
            foreach ($subscriptions->data as $subscription) {
                if (isset($subscription->metadata['type']) &&
                    $subscription->metadata['type'] === 'sliding_scale_membership' &&
                    $subscription->cancel_at_period_end) {
                    $membershipSubscription = $subscription;
                    break;
                }
            }

            if (!$membershipSubscription) {
                return [
                    'success' => false,
                    'error' => 'No cancelled subscription found to resume',
                ];
            }

            // Unset cancel at period end
            $membershipSubscription->cancel_at_period_end = false;
            $membershipSubscription->save();

            // Update our local subscription record to clear the cancellation
            $localSubscription = $user->subscriptions()->where('stripe_id', $membershipSubscription->id)->first();
            if ($localSubscription) {
                $localSubscription->update([
                    'ends_at' => null,
                ]);
                
                \Log::info('Cleared local subscription cancellation', [
                    'user_id' => $user->id,
                    'subscription_id' => $membershipSubscription->id,
                ]);
            }

            return [
                'success' => true,
                'message' => 'Subscription resumed successfully.',
                'subscription_id' => $membershipSubscription->id,
            ];
        } catch (ApiErrorException $e) {
            \Log::error('Stripe API error resuming subscription', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Payment service error: ' . $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync Stripe subscription data with local Cashier subscription.
     */
    public function syncStripeSubscription(User $user, array $stripeSubscription): void
    {
        try {
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

        } catch (\Exception $e) {
            \Log::error('Error syncing Stripe subscription', [
                'user_id' => $user->id,
                'subscription_id' => $stripeSubscription['id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update user membership status based on current subscriptions and transactions.
     */
    public function updateUserMembershipStatus(User $user): void
    {
        try {
            $shouldBeSustainingMember = false;

            // Check if user has active Stripe subscription above threshold
            if ($user->hasStripeId()) {
                $displayInfo = static::getSubscriptionDisplayInfo($user);
                if ($displayInfo['has_subscription'] && $displayInfo['amount'] >= self::SUSTAINING_MEMBER_THRESHOLD) {
                    $shouldBeSustainingMember = true;
                }
            }

            // Also check recent transactions (for Zeffy/other payment methods)
            if (!$shouldBeSustainingMember) {
                $recentTransaction = static::getMostRecentRecurringTransaction($user);
                if ($recentTransaction && 
                    $recentTransaction->amount->getAmount()->toFloat() >= self::SUSTAINING_MEMBER_THRESHOLD &&
                    $recentTransaction->created_at->isAfter(now()->subMonth())) {
                    $shouldBeSustainingMember = true;
                }
            }

            // Update role accordingly
            if ($shouldBeSustainingMember && !$user->hasRole('sustaining member')) {
                $user->assignRole('sustaining member');
                \Log::info('Assigned sustaining member role', ['user_id' => $user->id]);
            } elseif (!$shouldBeSustainingMember && $user->hasRole('sustaining member')) {
                $user->removeRole('sustaining member');
                \Log::info('Removed sustaining member role', ['user_id' => $user->id]);
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
}
