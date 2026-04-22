<?php

use App\Models\User;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\Models\LineItem;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->order = Order::create([
        'user_id' => $this->user->id,
        'total_amount' => 3000,
    ]);
});

// =========================================================================
// Creation
// =========================================================================

describe('LineItem creation', function () {
    it('creates a base line item', function () {
        $item = LineItem::create([
            'order_id' => $this->order->id,
            'product_type' => 'rehearsal_time',
            'product_id' => 42,
            'description' => 'Practice Space - 2 hours on Apr 22, 2026',
            'unit' => 'hour',
            'unit_price' => 1500,
            'quantity' => 2,
            'amount' => 3000,
        ]);

        expect($item->product_type)->toBe('rehearsal_time');
        expect($item->product_id)->toBe(42);
        expect($item->unit_price)->toBe(1500);
        expect($item->quantity)->toBe('2.00'); // decimal cast
        expect($item->amount)->toBe(3000);
    });

    it('creates a discount line item with negative amount', function () {
        $item = LineItem::create([
            'order_id' => $this->order->id,
            'product_type' => 'sustaining_member_discount',
            'description' => '4 free hour blocks applied',
            'unit' => 'discount',
            'unit_price' => -750,
            'quantity' => 4,
            'amount' => -3000,
        ]);

        expect($item->amount)->toBe(-3000);
        expect($item->product_id)->toBeNull();
    });

    it('creates a category line item without product_id', function () {
        $item = LineItem::create([
            'order_id' => $this->order->id,
            'product_type' => 'processing_fee',
            'description' => 'Processing fee',
            'unit' => 'fee',
            'unit_price' => 117,
            'quantity' => 1,
            'amount' => 117,
        ]);

        expect($item->product_id)->toBeNull();
        expect($item->product_type)->toBe('processing_fee');
    });
});

// =========================================================================
// Relationships
// =========================================================================

describe('LineItem relationships', function () {
    it('belongs to an order', function () {
        $item = LineItem::create([
            'order_id' => $this->order->id,
            'product_type' => 'rehearsal_time',
            'description' => 'Practice Space',
            'unit' => 'hour',
            'unit_price' => 1500,
            'quantity' => 2,
            'amount' => 3000,
        ]);

        expect($item->order->id)->toBe($this->order->id);
    });
});

// =========================================================================
// Helper methods
// =========================================================================

describe('LineItem helpers', function () {
    it('identifies discounts by negative amount', function () {
        $base = LineItem::create([
            'order_id' => $this->order->id,
            'product_type' => 'rehearsal_time',
            'description' => 'Practice Space',
            'unit' => 'hour',
            'unit_price' => 1500,
            'quantity' => 2,
            'amount' => 3000,
        ]);

        $discount = LineItem::create([
            'order_id' => $this->order->id,
            'product_type' => 'sustaining_member_discount',
            'description' => 'Free hours applied',
            'unit' => 'discount',
            'unit_price' => -750,
            'quantity' => 2,
            'amount' => -1500,
        ]);

        expect($base->isDiscount())->toBeFalse();
        expect($discount->isDiscount())->toBeTrue();
    });
});

// =========================================================================
// Order total consistency
// =========================================================================

describe('LineItem and Order total', function () {
    it('line items sum to order total', function () {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 1500, // 3000 base - 1500 discount
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

        LineItem::create([
            'order_id' => $order->id,
            'product_type' => 'sustaining_member_discount',
            'description' => '2 free hour blocks',
            'unit' => 'discount',
            'unit_price' => -750,
            'quantity' => 2,
            'amount' => -1500,
        ]);

        $lineItemTotal = $order->lineItems->sum('amount');

        expect($lineItemTotal)->toBe($order->total_amount);
    });
});
