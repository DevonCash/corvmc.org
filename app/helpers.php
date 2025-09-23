<?php

use App\Models\User;

if (!function_exists('auth_user')) {
    /**
     * Get the authenticated user with proper typing.
     */
    function auth_user(): ?User
    {
        return auth()->user();
    }
}