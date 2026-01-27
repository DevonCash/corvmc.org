<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Event Ticketing Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for native CMC event ticketing system.
    |
    */

    /**
     * Default ticket price in cents ($10.00).
     */
    'default_price' => env('TICKETING_DEFAULT_PRICE', 1000),

    /**
     * Sustaining member discount percentage (50% off).
     */
    'sustaining_member_discount' => env('TICKETING_SUSTAINING_MEMBER_DISCOUNT', 50),

    /**
     * Maximum tickets per order.
     */
    'max_tickets_per_order' => env('TICKETING_MAX_PER_ORDER', 10),

    /**
     * Stripe product name for ticket line items.
     */
    'stripe_product_name' => env('TICKETING_STRIPE_PRODUCT_NAME', 'Event Ticket'),

    /**
     * Stripe processing fee percentage (Stripe takes 2.9% + $0.30).
     */
    'stripe_fee_percentage' => 2.9,
    'stripe_fee_fixed' => 30, // cents
];
