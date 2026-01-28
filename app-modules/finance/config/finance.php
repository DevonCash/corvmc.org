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
];
