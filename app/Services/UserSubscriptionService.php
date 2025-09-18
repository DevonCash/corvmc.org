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
        return Cache::remember("user.{$user->id}.is_sustaining", 3600, function () use ($user) {
            // Check if they have the role assigned
            if ($user->hasRole('sustaining member')) {
                return true;
            }

            // Check for recent recurring transactions over threshold
            return $user->transactions()
                ->where('type', 'recurring')
                ->where('amount', '>', self::SUSTAINING_MEMBER_THRESHOLD)
                ->where('created_at', '>=', now()->subMonth())
                ->exists();
        });
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
            return User::whereHas('roles', function ($query) {
                $query->where('name', 'sustaining member');
            })->orWhereHas('transactions', function ($query) {
                $query->where('type', 'recurring')
                    ->where('amount', '>', self::SUSTAINING_MEMBER_THRESHOLD)
                    ->where('created_at', '>=', now()->subMonth());
            })->with(['profile', 'transactions'])->get();
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
                ->where('amount', '>', self::SUSTAINING_MEMBER_THRESHOLD)
                ->where('created_at', '<=', $cutoffDate);
        })->whereDoesntHave('transactions', function ($query) {
            $query->where('type', 'recurring')
                ->where('amount', '>', self::SUSTAINING_MEMBER_THRESHOLD)
                ->where('created_at', '>=', now()->subMonth());
        })->with(['profile', 'transactions'])->get();
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
            $totalAmount = $breakdown['total_amount'];

            // Create checkout session
            $checkout = $user->checkout([
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => (int) $totalAmount->getMinorAmount(),
                        'recurring' => ['interval' => 'month'],
                        'product_data' => [
                            'name' => 'Sliding Scale Membership',
                            'description' => $breakdown['description'],
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'success_url' => route('filament.member.auth.profile') . '?membership=success',
                'cancel_url' => route('filament.member.auth.profile') . '?membership=cancelled',
                'subscription_data' => [
                    'metadata' => [
                        'covers_fees' => $coverFees ? 'true' : 'false',
                    ],
                ],
            ]);

            return [
                'success' => true,
                'checkout_url' => $checkout->url,
                'breakdown' => $breakdown,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update an existing Stripe subscription amount.
     */
    public function updateSubscriptionAmount(User $user, float $baseAmount, bool $coverFees = false): array
    {
        try {
            $subscription = $user->subscription('default');

            if (!$subscription || !$subscription->active()) {
                return [
                    'success' => false,
                    'error' => 'No active subscription found',
                ];
            }

            $breakdown = PaymentService::getFeeBreakdown($baseAmount, $coverFees);
            $totalAmount = $breakdown['total_amount'];

            // Update the subscription with new pricing
            $subscription->swap([
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => PaymentService::dollarsToStripeAmount($totalAmount),
                    'recurring' => ['interval' => 'month'],
                    'product_data' => [
                        'name' => 'Sliding Scale Membership',
                        'description' => $breakdown['description'],
                    ],
                ],
            ]);

            return [
                'success' => true,
                'breakdown' => $breakdown,
                'message' => 'Membership amount updated successfully. Changes will take effect with your next billing cycle.',
            ];
        } catch (\Exception $e) {
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
        $subscription = $user->subscription('default');

        if (!$subscription || !$subscription->active()) {
            return [
                'has_subscription' => false,
                'status' => 'No active subscription',
                'amount' => 0,
            ];
        }

        $price = $subscription->items->first()?->price ?? null;
        $amount = $price ? PaymentService::stripeAmountToDollars($price->unit_amount) : 0;

        return [
            'has_subscription' => true,
            'status' => ucfirst($subscription->stripe_status),
            'amount' => $amount,
            'formatted_amount' => PaymentService::formatMoney($amount),
            'interval' => $price->recurring->interval ?? 'month',
            'next_billing' => $subscription->asStripeSubscription()->current_period_end,
        ];
    }

    /**
     * Get fee calculation for display purposes.
     */
    public function getFeeCalculation(float $baseAmount, bool $coverFees = false): array
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
            $subscription = $user->subscription('default');

            if (!$subscription || !$subscription->active()) {
                return [
                    'success' => false,
                    'error' => 'No active subscription found',
                ];
            }

            $subscription->cancel();

            return [
                'success' => true,
                'message' => 'Subscription cancelled successfully. You will retain access until the end of your current billing period.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Resume a cancelled subscription.
     */
    public function resumeSubscription(User $user): array
    {
        try {
            $subscription = $user->subscription('default');

            if (!$subscription || !$subscription->cancelled()) {
                return [
                    'success' => false,
                    'error' => 'No cancelled subscription found',
                ];
            }

            $subscription->resume();

            return [
                'success' => true,
                'message' => 'Subscription resumed successfully.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
