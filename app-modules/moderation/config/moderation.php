<?php

return [
    'enable_moderation' => env('MODERATION_ENABLE', false),

    'thresholds' => [
        'trusted' => 5,
        'verified' => 15,
        'auto_approved' => 30,
    ],

    'points' => [
        'successful_content' => 1,
        'minor_violation' => -3,
        'major_violation' => -5,
        'spam_violation' => -10,
    ],
];
