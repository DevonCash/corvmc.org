<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class UserSubscriptionService
{
    const SUSTAINING_MEMBER_THRESHOLD = 10.00;
    const FREE_HOURS_PER_MONTH = 4;

    /**
     * Check if a user qualifies as a sustaining member.
     */
    public function isSustainingMember(User $user): bool
    {
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
    }

    /**
     * Get user's subscription status and details.
     */
    public function getSubscriptionStatus(User $user): array
    {
        $isSustaining = $this->isSustainingMember($user);
        $recentTransaction = $this->getMostRecentRecurringTransaction($user);
        
        return [
            'is_sustaining_member' => $isSustaining,
            'free_hours_per_month' => $isSustaining ? self::FREE_HOURS_PER_MONTH : 0,
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
    public function processTransaction(Transaction $transaction): bool
    {
        $user = $transaction->user;
        if (!$user) {
            return false;
        }

        // If this is a recurring transaction over the threshold, 
        // potentially assign sustaining member role
        if ($transaction->type === 'recurring' && 
            $transaction->amount > self::SUSTAINING_MEMBER_THRESHOLD) {
            
            if (!$user->hasRole('sustaining member')) {
                $user->assignRole('sustaining member');
            }
        }

        return true;
    }

    /**
     * Get all sustaining members.
     */
    public function getSustainingMembers(): Collection
    {
        return User::whereHas('roles', function ($query) {
            $query->where('name', 'sustaining member');
        })->orWhereHas('transactions', function ($query) {
            $query->where('type', 'recurring')
                  ->where('amount', '>', self::SUSTAINING_MEMBER_THRESHOLD)
                  ->where('created_at', '>=', now()->subMonth());
        })->with(['profile', 'transactions'])->get();
    }

    /**
     * Get subscription statistics.
     */
    public function getSubscriptionStats(): array
    {
        $totalUsers = User::count();
        $sustainingMembers = $this->getSustainingMembers()->count();
        
        $recentTransactions = Transaction::where('type', 'recurring')
            ->where('created_at', '>=', now()->subMonth())
            ->get();

        $totalMonthlyRevenue = $recentTransactions->sum('amount');
        $averageSubscription = $recentTransactions->avg('amount') ?? 0;

        return [
            'total_users' => $totalUsers,
            'sustaining_members' => $sustainingMembers,
            'sustaining_percentage' => $totalUsers > 0 ? ($sustainingMembers / $totalUsers) * 100 : 0,
            'monthly_revenue' => $totalMonthlyRevenue,
            'average_subscription' => $averageSubscription,
            'total_free_hours_allocated' => $sustainingMembers * self::FREE_HOURS_PER_MONTH,
        ];
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

        return [
            'month' => $month->format('Y-m'),
            'total_reservations' => $reservations->count(),
            'total_hours' => $totalHours,
            'free_hours_used' => $totalFreeHours,
            'paid_hours' => $totalHours - $totalFreeHours,
            'total_cost' => $totalPaid,
            'allocated_free_hours' => $this->isSustainingMember($user) ? self::FREE_HOURS_PER_MONTH : 0,
            'unused_free_hours' => max(0, self::FREE_HOURS_PER_MONTH - $totalFreeHours),
        ];
    }

    /**
     * Get the most recent recurring transaction for a user.
     */
    protected function getMostRecentRecurringTransaction(User $user): ?Transaction
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
        if (!$user->hasRole('sustaining member')) {
            $user->assignRole('sustaining member');
            return true;
        }

        return false;
    }
}