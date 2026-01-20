<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Revision System Enabled
    |--------------------------------------------------------------------------
    |
    | This determines whether the revision/approval system is active globally.
    | When disabled, all content updates are applied immediately without
    | requiring approval, regardless of individual model settings.
    |
    | - true: Revision system active (default behavior)
    | - false: All updates apply immediately, bypass revision system
    |
    */
    'enabled' => env('REVISIONS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Show Revision Resource in Admin Panel
    |--------------------------------------------------------------------------
    |
    | Controls whether the Revision resource appears in the Filament admin panel.
    | When revisions are disabled, you may want to hide the resource entirely.
    |
    */
    'show_resource' => env('REVISIONS_SHOW_RESOURCE', true),
];
