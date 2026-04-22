<?php

use App\Models\User;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\Models\LineItem;
use CorvMC\Finance\Models\Transaction;
use CorvMC\Finance\States\OrderState;
use CorvMC\Finance\States\OrderState\Pending;
use CorvMC\Finance\States\OrderState\Completed;
use CorvMC\Finance\States\OrderState\Comped;
use CorvMC\Finance\States\OrderState\Refunded;
use CorvMC\Finance\States\OrderState\Cancelled;
use CorvMC\Finance\States\TransactionState\Cleared;
use CorvMC\Finance\States\TransactionState\Pending as TransactionPending;

beforeEach(function () {
    $this->user = User::factory()->create();
});

// =========================================================================
// Creation & Defaults
// =========================================================================

describe('Order creation', function () {
    it('creates an order with default Pending status', function () {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 3000,
        ]);

        expect($order->status)->toBeInstanceOf(Pending::class);
        expect($order->total_amount)->toBe(3000);
        expect($order->settled_at)->toBeNull();
    });

    it('supports guest orders with null user_id', function () {
        $order = Order::create([
            'user_id' => null,
            'total_amount' => 1500,
        ]);

        expect($order->user_id)->toBeNull();
        expect($order->user)->toBeNull();
    });
});

// =========================================================================
// Relationships
// =========================================================================

describe('Order relationships', function () {
    it('belongs to a user', function () {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 3000,
        ]);

        expect($order->user->id)->toBe($this->user->id);
    });

    it('has many line items', function () {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 3000,
        ]);

        LineItem::create([
            'order_id' => $order->id,
            'product_type' => 'rehearsal_time',
            'description' => 'Practice Space - 2 hours',
            'unit' => 'hour',
            'unit_price' => 1500,
            'quantity' => 2,
            'amount' => 3000,
        ]);

        expect($order->lineItems)->toHaveCount(1);
        expect($order->lineItems->first()->amount)->toBe(3000);
    });

    it('has many transactions', function () {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 3000,
        ]);

        Transaction::create([
            'order_id' => $order->id,
            'user_id' => $this->user->id,
            'currency' => 'stripe',
            'amount' => -3000,
            'type' => 'payment',
        ]);

        expect($order->transactions)->toHaveCount(1);
    });

    it('is accessible from User->orders()', function () {
        Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 3000,
        ]);

        expect($this->user->orders)->toHaveCount(1);
    });
});

// =========================================================================
// State transitions
// =========================================================================

describe('Order state transitions', function () {
    it('transitions Pending → Completed', function () {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 3000,
        ]);

        $order->status->transitionTo(Completed::class);

        expect($order->fresh()->status)->toBeInstanceOf(Completed::class);
    });

    it('transitions Pending → Comped', function () {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 3000,
        ]);

        $order->status->transitionTo(Comped::class);

        expect($order->fresh()->status)->toBeInstanceOf(Comped::class);
    });

    it('transitions Pending → Cancelled', function () {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 3000,
        ]);

        $order->status->transitionTo(Cancelled::class);

        expect($order->fresh()->status)->toBeInstanceOf(Cancelled::class);
    });

    it('transitions Completed → Refunded', function () {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 3000,
            'status' => Completed::getMorphClass(),
        ]);

        $order->status->transitionTo(Refunded::class);

        expect($order->fresh()->status)->toBeInstanceOf(Refunded::class);
    });

    it('transitions Comped → Refunded', function () {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 3000,
            'status' => Comped::getMorphClass(),
        ]);

        $order->status->transitionTo(Refunded::class);

        expect($order->fresh()->status)->toBeInstanceOf(Refunded::class);
    });

    it('rejects invalid transition Pending → Refunded', function () {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 3000,
        ]);

        $order->status->transitionTo(Refunded::class);
    })->throws(\Spatie\ModelStates\Exceptions\TransitionNotFound::class);

    it('rejects invalid transition Completed → Pending', function () {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 3000,
            'status' => Completed::getMorphClass(),
        ]);

        $order->status->transitionTo(Pending::class);
    })->throws(\Spatie\ModelStates\Exceptions\TransitionNotFound::class);

    it('rejects invalid transition Cancelled → Completed', function () {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 3000,
            'status' => Cancelled::getMorphClass(),
        ]);

        $order->status->transitionTo(Completed::class);
    })->throws(\Spatie\ModelStates\Exceptions\TransitionNotFound::class);
});

// =========================================================================
// Helper methods
// =========================================================================

describe('Order helper methods', function () {
    it('reports isSettled for Completed orders', function () {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 3000,
            'status' => Completed::getMorphClass(),
        ]);

        expect($order->isSettled())->toBeTrue();
    });

    it('reports isSettled for Comped orders', function () {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 0,
            'status' => Comped::getMorphClass(),
        ]);

        expect($order->isSettled())->toBeTrue();
    });

    it('reports not isSettled for Pending orders', function () {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 3000,
        ]);

        expect($order->isSettled())->toBeFalse();
    });

    it('reports isTerminal for Refunded and Cancelled (no outbound transitions)', function () {
        foreach ([Refunded::class, Cancelled::class] as $stateClass) {
            $order = Order::create([
                'user_id' => $this->user->id,
                'total_amount' => 3000,
                'status' => $stateClass::getMorphClass(),
            ]);

            expect($order->isTerminal())->toBeTrue("Expected {$stateClass} to be terminal");
        }
    });

    it('reports not isTerminal for Pending, Completed, Comped (have outbound transitions)', function () {
        foreach ([Pending::class, Completed::class, Comped::class] as $stateClass) {
            $order = Order::create([
                'user_id' => $this->user->id,
                'total_amount' => 3000,
                'status' => $stateClass::getMorphClass(),
            ]);

            expect($order->isTerminal())->toBeFalse("Expected {$stateClass} to not be terminal");
        }
    });

    it('calculates paidAmount from Cleared payment Transactions', function () {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 3000,
        ]);

        // Cleared payment — negative amount per sign convention
        Transaction::create([
            'order_id' => $order->id,
            'user_id' => $this->user->id,
            'currency' => 'stripe',
            'amount' => -3000,
            'type' => 'payment',
            'status' => Cleared::getMorphClass(),
            'cleared_at' => now(),
        ]);

        expect($order->paidAmount())->toBe(3000);
    });

    it('ignores Pending Transactions in paidAmount', function () {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 3000,
        ]);

        Transaction::create([
            'order_id' => $order->id,
            'user_id' => $this->user->id,
            'currency' => 'stripe',
            'amount' => -3000,
            'type' => 'payment',
        ]);

        expect($order->paidAmount())->toBe(0);
    });

    it('sums multiple Cleared payments in paidAmount', function () {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 3000,
        ]);

        // Stripe portion
        Transaction::create([
            'order_id' => $order->id,
            'user_id' => $this->user->id,
            'currency' => 'stripe',
            'amount' => -2000,
            'type' => 'payment',
            'status' => Cleared::getMorphClass(),
            'cleared_at' => now(),
        ]);

        // Cash portion
        Transaction::create([
            'order_id' => $order->id,
            'user_id' => $this->user->id,
            'currency' => 'cash',
            'amount' => -1000,
            'type' => 'payment',
            'status' => Cleared::getMorphClass(),
            'cleared_at' => now(),
        ]);

        expect($order->paidAmount())->toBe(3000);
    });

    it('calculates outstandingAmount correctly', function () {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 3000,
        ]);

        Transaction::create([
            'order_id' => $order->id,
            'user_id' => $this->user->id,
            'currency' => 'cash',
            'amount' => -1000,
            'type' => 'payment',
            'status' => Cleared::getMorphClass(),
            'cleared_at' => now(),
        ]);

        expect($order->outstandingAmount())->toBe(2000);
    });

    it('returns zero outstandingAmount when fully paid', function () {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 3000,
        ]);

        Transaction::create([
            'order_id' => $order->id,
            'user_id' => $this->user->id,
            'currency' => 'stripe',
            'amount' => -3000,
            'type' => 'payment',
            'status' => Cleared::getMorphClass(),
            'cleared_at' => now(),
        ]);

        expect($order->outstandingAmount())->toBe(0);
    });
});
