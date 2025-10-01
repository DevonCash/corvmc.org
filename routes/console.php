<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule reservation reminders to be sent daily at 10 AM
Schedule::command('reservations:send-reminders')->dailyAt('10:00');

// Schedule confirmation reminders to be sent daily at 9 AM (before regular reminders)
Schedule::command('reservations:send-confirmation-reminders')->dailyAt('09:00');

// Schedule membership reminders to be sent daily at 11 AM
Schedule::command('memberships:send-reminders')->dailyAt('11:00');

// Schedule monthly credit allocation
Schedule::command('credits:allocate')->daily();

// Generate future instances for recurring reservations daily
Schedule::call(function () {
    app(\App\Services\RecurringReservationService::class)->generateFutureInstances();
})->daily()->at('00:00');
