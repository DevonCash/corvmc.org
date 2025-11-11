<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GoogleCalendarSettings extends Settings
{
    public bool $enable_google_calendar_sync;

    public ?string $google_calendar_id;

    public static function group(): string
    {
        return 'google_calendar';
    }
}
