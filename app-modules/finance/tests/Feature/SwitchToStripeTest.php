<?php

use App\Models\User;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\Models\Transaction;
use CorvMC\Finance\States\OrderState\Completed;
use CorvMC\Finance\States\OrderState\Pending as OrderPending;
use CorvMC\Finance\States\TransactionState\Cancelled as TransactionCancelled;
use CorvMC\Finance\States\TransactionState\Failed as TransactionFailed;
use CorvMC\Finance\States\TransactionState\Pending as TransactionPending;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->assignRole('member');

    // Mock checkoutCharge on the User model to avoid hitting Stripe
    $this->fakeCheckout = new class
    {
        public string $id = 'cs_test_fake_session_123';
        public string $url = 'https://checkout.stripe.com/fake-session';
    };
});

// ---------------------------------------------------------------------------
// switchToStripe
// ---------------------------------------------------------------------------

it('switches a pending cash transaction to a stripe checkout', function () {
    $order = Order::create([
        'user_id' => $this->user->id,
        'total_amount' => 3000,
    ]);

    Transaction::create([
        'order_id' => $order->id,
        'user_id' => $this->user->id,
        'currency' => 'cash',
        'amount' => 3000,
        'type' => 'payment',
        'metadata' => [],
    ]);

    // Mock checkoutCharge
    $mock = Mockery::mock($this->user)->makePartial();
    $mock->shouldReceive('checkoutCharge')->once()->andReturn($this->fakeCheckout);
    $order->setRelation('user', $mock);

    // Bind the mock user so FinanceManager sees it via $order->user
    $this->app->instance('finance.test.order', $order);
    $manager = app(\CorvMC\Finance\FinanceManager::class);

    // Use reflection to call with our order that has the mocked user relation
    $url = $manager->switchToStripe($order);

    expect($url)->toBe('https://checkout.stripe.com/fake-session');

    // Cash transaction should be cancelled
    $cashTxn = $order->transactions()->where('currency', 'cash')->first();
    expect($cashTxn->status)->toBeInstanceOf(TransactionCancelled::class);

    // New stripe transaction should exist
    $stripeTxn = $order->transactions()->where('currency', 'stripe')->first();
    expect($stripeTxn)->not->toBeNull()
        ->and($stripeTxn->amount)->toBe(3000)
        ->and($stripeTxn->type)->toBe('payment')
        ->and($stripeTxn->status)->toBeInstanceOf(TransactionPending::class)
        ->and($stripeTxn->metadata['session_id'])->toBe('cs_test_fake_session_123')
        ->and($stripeTxn->metadata['checkout_url'])->toBe('https://checkout.stripe.com/fake-session')
        ->and($stripeTxn->metadata['switched_from'])->toBe($cashTxn->id);
});

it('returns null when no pending cash transaction exists', function () {
    $order = Order::create([
        'user_id' => $this->user->id,
        'total_amount' => 3000,
    ]);

    // No transactions at all
    $url = Finance::switchToStripe($order);

    expect($url)->toBeNull();
});

it('returns null when cash transaction is already cancelled', function () {
    $order = Order::create([
        'user_id' => $this->user->id,
        'total_amount' => 3000,
    ]);

    $txn = Transaction::create([
        'order_id' => $order->id,
        'user_id' => $this->user->id,
        'currency' => 'cash',
        'amount' => 3000,
        'type' => 'payment',
        'metadata' => [],
    ]);

    $txn->status->transitionTo(TransactionCancelled::class);

    $url = Finance::switchToStripe($order);

    expect($url)->toBeNull();
});

it('throws when order is not pending', function () {
    $order = Order::create([
        'user_id' => $this->user->id,
        'total_amount' => 0,
    ]);

    // Transition to Completed (allowed when total is 0 or no pending txns)
    $order->status->transitionTo(Completed::class);

    Finance::switchToStripe($order->fresh());
})->throws(RuntimeException::class, 'expected Pending');

it('preserves order total when switching payment method', function () {
    $order = Order::create([
        'user_id' => $this->user->id,
        'total_amount' => 4500,
    ]);

    Transaction::create([
        'order_id' => $order->id,
        'user_id' => $this->user->id,
        'currency' => 'cash',
        'amount' => 4500,
        'type' => 'payment',
        'metadata' => [],
    ]);

    $mock = Mockery::mock($this->user)->makePartial();
    $mock->shouldReceive('checkoutCharge')
        ->once()
        ->withArgs(function ($amount) {
            return $amount === 4500;
        })
        ->andReturn($this->fakeCheckout);
    $order->setRelation('user', $mock);

    $manager = app(\CorvMC\Finance\FinanceManager::class);
    $manager->switchToStripe($order);

    // Order total should be unchanged
    expect($order->fresh()->total_amount)->toBe(4500);

    // Stripe transaction should have the same amount as the cancelled cash one
    $stripeTxn = $order->transactions()->where('currency', 'stripe')->first();
    expect($stripeTxn->amount)->toBe(4500);
});

it('preserves cash transaction when stripe checkout creation fails', function () {
    $order = Order::create([
        'user_id' => $this->user->id,
        'total_amount' => 3000,
    ]);

    $cashTxn = Transaction::create([
        'order_id' => $order->id,
        'user_id' => $this->user->id,
        'currency' => 'cash',
        'amount' => 3000,
        'type' => 'payment',
        'metadata' => [],
    ]);

    $mock = Mockery::mock($this->user)->makePartial();
    $mock->shouldReceive('checkoutCharge')->once()->andThrow(new \Exception('Stripe API error'));
    $order->setRelation('user', $mock);

    $manager = app(\CorvMC\Finance\FinanceManager::class);

    try {
        $manager->switchToStripe($order);
    } catch (\Exception $e) {
        // Expected
    }

    // Cash transaction should still be Pending — never cancelled since
    // the stripe checkout is attempted before touching cash
    expect($cashTxn->fresh()->status)->toBeInstanceOf(TransactionPending::class);

    // Order should still be in a payable state
    expect($order->fresh()->status)->toBeInstanceOf(OrderPending::class);
});

// ---------------------------------------------------------------------------
// retryStripePayment (regression tests after refactor)
// ---------------------------------------------------------------------------

it('retries a failed stripe payment with a new checkout session', function () {
    $order = Order::create([
        'user_id' => $this->user->id,
        'total_amount' => 2000,
    ]);

    $failedTxn = Transaction::create([
        'order_id' => $order->id,
        'user_id' => $this->user->id,
        'currency' => 'stripe',
        'amount' => 2000,
        'type' => 'payment',
        'metadata' => [],
    ]);

    $failedTxn->status->transitionTo(TransactionFailed::class);

    $mock = Mockery::mock($this->user)->makePartial();
    $mock->shouldReceive('checkoutCharge')->once()->andReturn($this->fakeCheckout);
    $order->setRelation('user', $mock);

    $manager = app(\CorvMC\Finance\FinanceManager::class);
    $url = $manager->retryStripePayment($order);

    expect($url)->toBe('https://checkout.stripe.com/fake-session');

    // Original failed transaction unchanged
    expect($failedTxn->fresh()->status)->toBeInstanceOf(TransactionFailed::class);

    // New retry transaction created
    $retryTxn = $order->transactions()
        ->where('currency', 'stripe')
        ->whereState('status', TransactionPending::class)
        ->first();

    expect($retryTxn)->not->toBeNull()
        ->and($retryTxn->amount)->toBe(2000)
        ->and($retryTxn->metadata['retry_of'])->toBe($failedTxn->id)
        ->and($retryTxn->metadata['session_id'])->toBe('cs_test_fake_session_123');
});

it('returns null for retry when no failed stripe transaction exists', function () {
    $order = Order::create([
        'user_id' => $this->user->id,
        'total_amount' => 2000,
    ]);

    // Only a pending cash transaction, no failed stripe
    Transaction::create([
        'order_id' => $order->id,
        'user_id' => $this->user->id,
        'currency' => 'cash',
        'amount' => 2000,
        'type' => 'payment',
        'metadata' => [],
    ]);

    $url = Finance::retryStripePayment($order);

    expect($url)->toBeNull();
});
