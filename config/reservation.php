<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Practice Space Reservations
    |--------------------------------------------------------------------------
    |
    | Configuration for practice space reservations and credit management.
    |
    */

    /**
     * Number of minutes per credit block.
     * Credits are tracked in blocks for precision and flexibility.
     */
    'minutes_per_block' => env('RESERVATION_MINUTES_PER_BLOCK', 30),

    /**
     * Hourly rate for practice space in dollars.
     */
    'hourly_rate' => env('RESERVATION_HOURLY_RATE', 15.00),

    /**
     * Business hours (24-hour format).
     */
    'business_hours' => [
        'start' => 9,  // 9 AM
        'end' => 22,   // 10 PM
    ],

    /**
     * Reservation duration limits in hours.
     */
    'duration' => [
        'min' => 1,
        'max' => 8,
    ],
];
