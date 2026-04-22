<?php

use CorvMC\SpaceManagement\Models\RehearsalReservation;

return [
    /*
    |--------------------------------------------------------------------------
    | Pricing Configuration
    |--------------------------------------------------------------------------
    |
    | Define pricing rates for Chargeable models by their class name.
    | Rate is in cents, unit describes what the rate applies to.
    |
    | Example: RehearsalReservation at 1500 cents per hour = $15/hour
    |
    */

    'pricing' => [
        RehearsalReservation::class => [
            'rate' => 1500, // cents per unit
            'unit' => 'hour',
        ],
        // Add other chargeables here:
        // EquipmentLoan::class => ['rate' => 0, 'unit' => 'day'],
        // EventTicket::class => ['rate' => 500, 'unit' => 'ticket'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit Configuration
    |--------------------------------------------------------------------------
    |
    | Map credit types to chargeables and define application rules.
    |
    */

    'credits' => [
        // Which credit type applies to which chargeable
        'applicable' => [
            RehearsalReservation::class => 'free_hours',
        ],

        // How credits translate to monetary value (per credit block)
        'value' => [
            'free_hours' => 750, // cents - each credit block = $7.50 (30 min at $15/hr)
        ],

        // Minutes per credit block (for time-based credits)
        'minutes_per_block' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Wallets (New — used by Product system)
    |--------------------------------------------------------------------------
    |
    | Each key is a wallet type (matches CreditType enum values).
    | cents_per_unit: monetary value of one credit block in that wallet.
    | label: human-readable name for receipts / UI.
    |
    */

    'wallets' => [
        'free_hours' => [
            'cents_per_unit' => 750, // $7.50 per 30-min block
            'label' => 'Free rehearsal hours',
        ],
        'equipment_credits' => [
            'cents_per_unit' => 100,
            'label' => 'Equipment credits',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing Fee
    |--------------------------------------------------------------------------
    |
    | Stripe-style fee structure applied as a LineItem on Orders paid via card.
    | rate_bps: basis-point surcharge (290 = 2.9%)
    | fixed_cents: flat per-transaction charge in cents
    |
    */

    'processing_fee' => [
        'rate_bps' => 290,
        'fixed_cents' => 30,
    ],
];
