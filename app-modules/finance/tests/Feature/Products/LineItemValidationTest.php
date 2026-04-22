<?php

use App\Models\User;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\Models\LineItem;

// =========================================================================
// LineItem product_type validation
// =========================================================================

describe('LineItem product_type validation', function () {
    it('allows creating a LineItem with a registered product_type', function () {
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'total_amount' => 3000,
        ]);

        $lineItem = LineItem::create([
            'order_id' => $order->id,
            'product_type' => 'rehearsal_time',
            'description' => 'Practice Space - 2 hours',
            'unit' => 'hour',
            'unit_price' => 1500,
            'quantity' => 2,
            'amount' => 3000,
        ]);

        expect($lineItem->exists)->toBeTrue();
        expect($lineItem->product_type)->toBe('rehearsal_time');
    });

    it('allows all registered product types', function () {
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'total_amount' => 0,
        ]);

        $types = [
            'rehearsal_time',
            'event_ticket',
            'equipment_loan',
            'processing_fee',
            'sustaining_member_discount',
            'comp_discount',
            'manual_adjustment',
        ];

        foreach ($types as $type) {
            $lineItem = LineItem::create([
                'order_id' => $order->id,
                'product_type' => $type,
                'description' => "Test {$type}",
                'unit' => 'test',
                'unit_price' => 100,
                'quantity' => 1,
                'amount' => 100,
            ]);

            expect($lineItem->exists)->toBeTrue("Failed to create LineItem with product_type: {$type}");
        }
    });

    it('rejects LineItem with unregistered product_type', function () {
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'total_amount' => 3000,
        ]);

        LineItem::create([
            'order_id' => $order->id,
            'product_type' => 'nonexistent_widget',
            'description' => 'Should fail',
            'unit' => 'widget',
            'unit_price' => 100,
            'quantity' => 1,
            'amount' => 100,
        ]);
    })->throws(\RuntimeException::class, 'Unknown product_type [nonexistent_widget]');

    it('rejects updates that change product_type to unregistered value', function () {
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'total_amount' => 3000,
        ]);

        $lineItem = LineItem::create([
            'order_id' => $order->id,
            'product_type' => 'rehearsal_time',
            'description' => 'Practice Space',
            'unit' => 'hour',
            'unit_price' => 1500,
            'quantity' => 2,
            'amount' => 3000,
        ]);

        $lineItem->product_type = 'nonexistent_widget';
        $lineItem->save();
    })->throws(\RuntimeException::class, 'Unknown product_type [nonexistent_widget]');
});
