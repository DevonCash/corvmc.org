<?php

namespace CorvMC\Finance\Listeners;

use CorvMC\Finance\Events\TransactionCleared;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\States\OrderState\Completed;
use CorvMC\Finance\States\OrderState\Pending;
use CorvMC\Finance\States\TransactionState\Pending as TransactionPending;
use Illuminate\Support\Facades\DB;

/**
 * When a Transaction is cleared, check whether all payment Transactions
 * on the parent Order are now settled. If so, transition the Order to Completed.
 */
class CheckOrderSettlement
{
    public function handle(TransactionCleared $event): void
    {
        $transaction = $event->transaction;
        $order = $transaction->order;

        if (! $order) {
            return;
        }

        // Only act on Orders that are still Pending
        if (! ($order->status instanceof Pending)) {
            return;
        }

        DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->find($order->id);

            if (! $order || ! ($order->status instanceof Pending)) {
                return;
            }

            $hasPendingTransactions = $order->transactions()
                ->whereState('status', TransactionPending::class)
                ->exists();

            if (! $hasPendingTransactions) {
                $order->status->transitionTo(Completed::class);
            }
        });
    }
}
