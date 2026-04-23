<?php

namespace CorvMC\Finance\Events;

use CorvMC\Finance\Models\Transaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a Transaction transitions to Cleared.
 *
 * Listeners use this to check whether the parent Order is fully
 * settled and should transition to Completed.
 */
class TransactionCleared
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Transaction $transaction
    ) {
    }
}
