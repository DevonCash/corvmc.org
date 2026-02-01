<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ReservationSettings extends Settings
{
    public int $buffer_minutes;

    public int $default_event_setup_minutes;

    public int $default_event_teardown_minutes;

    public static function group(): string
    {
        return 'reservation';
    }
}
