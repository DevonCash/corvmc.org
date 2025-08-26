<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Scheduling\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule reservation reminders to be sent daily at 10 AM
app(Schedule::class)->command('reservations:send-reminders')->dailyAt('10:00');

// Schedule confirmation reminders to be sent daily at 9 AM (before regular reminders)
app(Schedule::class)->command('reservations:send-confirmation-reminders')->dailyAt('09:00');

// Schedule membership reminders to be sent daily at 11 AM
app(Schedule::class)->command('memberships:send-reminders')->dailyAt('11:00');
