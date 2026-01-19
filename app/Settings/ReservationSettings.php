<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ReservationSettings extends Settings
{
    public int $buffer_minutes;

    public static function group(): string
    {
        return 'reservation';
    }
}
