<?php

namespace App\Observers;

use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;

class TransactionObserver
{
    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        $this->clearTransactionCaches($transaction);
    }

    /**
     * Handle the Transaction "updated" event.
     */
    public function updated(Transaction $transaction): void
    {
        $this->clearTransactionCaches($transaction);
    }

    /**
     * Handle the Transaction "deleted" event.
     */
    public function deleted(Transaction $transaction): void
    {
        $this->clearTransactionCaches($transaction);
    }

    /**
     * Clear all caches related to a transaction.
     */
    private function clearTransactionCaches(Transaction $transaction): void
    {
        // Clear subscription-related caches
        Cache::forget('sustaining_members');
        Cache::forget('subscription_stats');
        
        // Clear user-specific caches if we can find the user
        if ($transaction->email) {
            $user = \App\Models\User::where('email', $transaction->email)->first();
            if ($user) {
                Cache::forget("user.{$user->id}.is_sustaining");
                Cache::forget("user_stats.{$user->id}");
                Cache::forget("user_activity.{$user->id}");
            }
        }
    }
}