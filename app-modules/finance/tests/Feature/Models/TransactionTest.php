<?php

use App\Models\User;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\Models\Transaction;
use CorvMC\Finance\States\TransactionState\Pending;
use CorvMC\Finance\States\TransactionState\Cleared;
use CorvMC\Finance\States\TransactionState\Cancelled;
use CorvMC\Finance\States\TransactionState\Failed;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->order = Order::create([
        'user_id' => $this->user->id,
        'total_amount' => 3000,
    ]);
});

// =========================================================================
// Creation & Defaults
// =========================================================================

describe('Transaction creation', function () {
    it('creates a transaction with default Pending status', function () {
        $tx = Transaction::create([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'currency' => 'stripe',
            'amount' => 3000,
            'type' => 'payment',
        ]);

        expect($tx->status)->toBeInstanceOf(Pending::class);
        expect($tx->cleared_at)->toBeNull();
        expect($tx->cancelled_at)->toBeNull();
        expect($tx->failed_at)->toBeNull();
    });

    it('stores metadata as array', function () {
        $tx = Transaction::create([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'currency' => 'stripe',
            'amount' => 3000,
            'type' => 'payment',
            'metadata' => ['session_id' => 'cs_test_123'],
        ]);

        expect($tx->metadata)->toBe(['session_id' => 'cs_test_123']);
    });

    it('supports guest transactions with null user_id', function () {
        $tx = Transaction::create([
            'order_id' => $this->order->id,
            'user_id' => null,
            'currency' => 'stripe',
            'amount' => 1500,
            'type' => 'payment',
        ]);

        expect($tx->user_id)->toBeNull();
    });
});

// =========================================================================
// Relationships
// =========================================================================

describe('Transaction relationships', function () {
    it('belongs to an order', function () {
        $tx = Transaction::create([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'currency' => 'stripe',
            'amount' => 3000,
            'type' => 'payment',
        ]);

        expect($tx->order->id)->toBe($this->order->id);
    });

    it('belongs to a user', function () {
        $tx = Transaction::create([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'currency' => 'cash',
            'amount' => 3000,
            'type' => 'payment',
        ]);

        expect($tx->user->id)->toBe($this->user->id);
    });

    it('is accessible from User->transactions()', function () {
        Transaction::create([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'currency' => 'stripe',
            'amount' => 3000,
            'type' => 'payment',
        ]);

        expect($this->user->transactions)->toHaveCount(1);
    });
});

// =========================================================================
// State transitions
// =========================================================================

describe('Transaction state transitions', function () {
    it('transitions Pending → Cleared', function () {
        $tx = Transaction::create([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'currency' => 'stripe',
            'amount' => 3000,
            'type' => 'payment',
        ]);

        $tx->status->transitionTo(Cleared::class);

        expect($tx->fresh()->status)->toBeInstanceOf(Cleared::class);
    });

    it('transitions Pending → Cancelled', function () {
        $tx = Transaction::create([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'currency' => 'stripe',
            'amount' => 3000,
            'type' => 'payment',
        ]);

        $tx->status->transitionTo(Cancelled::class);

        expect($tx->fresh()->status)->toBeInstanceOf(Cancelled::class);
    });

    it('transitions Pending → Failed', function () {
        $tx = Transaction::create([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'currency' => 'stripe',
            'amount' => 3000,
            'type' => 'payment',
        ]);

        $tx->status->transitionTo(Failed::class);

        expect($tx->fresh()->status)->toBeInstanceOf(Failed::class);
    });

    it('rejects Cleared → Pending (terminal state)', function () {
        $tx = Transaction::create([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'currency' => 'stripe',
            'amount' => 3000,
            'type' => 'payment',
            'status' => Cleared::getMorphClass(),
        ]);

        $tx->status->transitionTo(Pending::class);
    })->throws(\Spatie\ModelStates\Exceptions\TransitionNotFound::class);

    it('rejects Cancelled → Cleared (terminal state)', function () {
        $tx = Transaction::create([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'currency' => 'stripe',
            'amount' => 3000,
            'type' => 'payment',
            'status' => Cancelled::getMorphClass(),
        ]);

        $tx->status->transitionTo(Cleared::class);
    })->throws(\Spatie\ModelStates\Exceptions\TransitionNotFound::class);

    it('rejects Failed → Cleared (terminal state)', function () {
        $tx = Transaction::create([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'currency' => 'stripe',
            'amount' => 3000,
            'type' => 'payment',
            'status' => Failed::getMorphClass(),
        ]);

        $tx->status->transitionTo(Cleared::class);
    })->throws(\Spatie\ModelStates\Exceptions\TransitionNotFound::class);
});

// =========================================================================
// Immutability
// =========================================================================

describe('Transaction immutability', function () {
    it('prevents changing amount after creation', function () {
        $tx = Transaction::create([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'currency' => 'stripe',
            'amount' => 3000,
            'type' => 'payment',
        ]);

        $tx->amount = 5000;
        $tx->save();
    })->throws(\RuntimeException::class, 'Transaction.amount is immutable once written.');

    it('prevents changing currency after creation', function () {
        $tx = Transaction::create([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'currency' => 'stripe',
            'amount' => 3000,
            'type' => 'payment',
        ]);

        $tx->currency = 'cash';
        $tx->save();
    })->throws(\RuntimeException::class, 'Transaction.currency is immutable once written.');

    it('prevents changing type after creation', function () {
        $tx = Transaction::create([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'currency' => 'stripe',
            'amount' => 3000,
            'type' => 'payment',
        ]);

        $tx->type = 'refund';
        $tx->save();
    })->throws(\RuntimeException::class, 'Transaction.type is immutable once written.');

    it('prevents changing order_id after creation', function () {
        $otherOrder = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 1000,
        ]);

        $tx = Transaction::create([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'currency' => 'stripe',
            'amount' => 3000,
            'type' => 'payment',
        ]);

        $tx->order_id = $otherOrder->id;
        $tx->save();
    })->throws(\RuntimeException::class, 'Transaction.order_id is immutable once written.');

    it('allows updating mutable fields (status, metadata, timestamps)', function () {
        $tx = Transaction::create([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'currency' => 'stripe',
            'amount' => 3000,
            'type' => 'payment',
        ]);

        $tx->metadata = ['payment_intent_id' => 'pi_123'];
        $tx->cleared_at = now();
        $tx->save();

        $tx->refresh();
        expect($tx->metadata)->toBe(['payment_intent_id' => 'pi_123']);
        expect($tx->cleared_at)->not->toBeNull();
    });
});

// =========================================================================
// Helper methods
// =========================================================================

describe('Transaction helper methods', function () {
    it('identifies payment type', function () {
        $tx = Transaction::create([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'currency' => 'stripe',
            'amount' => 3000,
            'type' => 'payment',
        ]);

        expect($tx->isPayment())->toBeTrue();
        expect($tx->isRefund())->toBeFalse();
        expect($tx->isFee())->toBeFalse();
    });

    it('identifies refund type', function () {
        $tx = Transaction::create([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'currency' => 'stripe',
            'amount' => 3000,
            'type' => 'refund',
        ]);

        expect($tx->isRefund())->toBeTrue();
        expect($tx->isPayment())->toBeFalse();
    });

    it('reports terminal states correctly', function () {
        $pendingTx = Transaction::create([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'currency' => 'stripe',
            'amount' => 3000,
            'type' => 'payment',
        ]);

        $clearedTx = Transaction::create([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'currency' => 'cash',
            'amount' => 1000,
            'type' => 'payment',
            'status' => Cleared::getMorphClass(),
        ]);

        expect($pendingTx->isTerminal())->toBeFalse();
        expect($clearedTx->isTerminal())->toBeTrue();
    });
});
