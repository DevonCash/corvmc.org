<?php

namespace App\Filament\Kiosk\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;

class Login extends BaseLogin
{
    /**
     * Disable "remember me" checkbox for kiosk
     */
    protected bool $hasRemember = false;
}
