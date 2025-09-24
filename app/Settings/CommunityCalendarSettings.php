<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class CommunityCalendarSettings extends Settings
{
    public bool $enable_community_calendar;
    
    public static function group(): string
    {
        return 'community_calendar';
    }
}