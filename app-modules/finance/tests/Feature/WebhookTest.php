<?php

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\Models\Transaction;
use CorvMC\Finance\States\TransactionState\Cleared;
use CorvMC\Finance\States\TransactionState\Failed;
use CorvMC\Finance\States\TransactionState\Pending as TransactionPending;
use CorvMC\SpaceManagement\Models\RehearsalReservation;

beforeEach(function () {
    $this->user = User::factory()->create();
});

/**
 * Create a committed Order with a Stripe Transaction that has session metadata.
 */
function createStripeOrder(object $test, string $sessionId = 'cs_test_123'): array
{
    $reservation = RehearsalReservation::factory()->create([
        'reservable_id' => $test->user->id,
        'reservable_type' => 'user',
        'reserved_at' => Carbon::tomorrow()->setTime(10, 0),
        'reserved_until' => Carbon::tomorrow()->setTime(12, 0),
    ]);

    $order = Order::create([
        'user_id' => $test->user->id,
        'total_amount' => 0,
    ]);

    $lineItems = Finance::price([$reservation]);
    foreach ($lineItems as $lineItem) {
        $lineItem->order_id = $order->id;
        $lineItem->save();
    }
    $order->update(['total_amount' => $lineItems->sum('amount')]);

    // Create a Stripe transaction manually (bypassing Stripe API)
    $txn = Transaction::create([
        'order_id' => $order->id,
        'user_id' => $test->user->id,
        'currency' => 'stripe',
        'amount' => $order->total_amount,
        'type' => 'payment',
        'metadata' => [
            'session_id' => $sessionId,
            'checkout_url' => 'https://checkout.stripe.com/test',
        ],
    ]);

    return ['order' => $order->fresh(['transactions']), 'transaction' => $txn, 'reservation' => $reservation];
}

// =========================================================================
// checkout.session.expired
// =========================================================================

describe('handleCheckoutSessionExpired', function () {
    it('transitions a pending Stripe transaction to Failed', function () {
        ['transaction' => $txn] = createStripeOrder($this, 'cs_expired_001');

        expect($txn->status)->toBeInstanceOf(TransactionPending::class);

        $payload = [
            'id' => 'evt_expired_001',
            'data' => [
                'object' => [
                    'id' => 'cs_expired_001',
                ],
            ],
        ];

        $controller = new \App\Http\Controllers\StripeWebhookController();
        $response = $controller->handleCheckoutSessionExpired($payload);

        expect($response->getStatusCode())->toBe(200);

        $txn->refresh();
        expect($txn->status)->toBeInstanceOf(Failed::class);
        expect($txn->failed_at)->not->toBeNull();
    });

    it('is idempotent — does not fail on duplicate events', function () {
        ['transaction' => $txn] = createStripeOrder($this, 'cs_expired_002');

        $payload = [
            'id' => 'evt_expired_002',
            'data' => ['object' => ['id' => 'cs_expired_002']],
        ];

        $controller = new \App\Http\Controllers\StripeWebhookController();
        $controller->handleCheckoutSessionExpired($payload);
        $response = $controller->handleCheckoutSessionExpired($payload);

        expect($response->getStatusCode())->toBe(200);
    });

    it('handles unknown session ID gracefully', function () {
        $payload = [
            'id' => 'evt_expired_003',
            'data' => ['object' => ['id' => 'cs_nonexistent']],
        ];

        $controller = new \App\Http\Controllers\StripeWebhookController();
        $response = $controller->handleCheckoutSessionExpired($payload);

        expect($response->getStatusCode())->toBe(200);
    });

    it('does not affect already-settled transactions', function () {
        ['transaction' => $txn] = createStripeOrder($this, 'cs_expired_004');

        // Settle the transaction first
        Finance::settle($txn, 'pi_test_settled');

        $payload = [
            'id' => 'evt_expired_004',
            'data' => ['object' => ['id' => 'cs_expired_004']],
        ];

        $controller = new \App\Http\Controllers\StripeWebhookController();
        $response = $controller->handleCheckoutSessionExpired($payload);

        expect($response->getStatusCode())->toBe(200);

        $txn->refresh();
        // Should still be Cleared, not Failed
        expect($txn->status)->toBeInstanceOf(Cleared::class);
    });
});
