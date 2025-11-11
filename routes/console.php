<?php

use App\Actions\Notifications\SendMembershipReminders;
use App\Actions\Notifications\SendReservationConfirmationReminders;
use App\Actions\Notifications\SendReservationReminders;
use App\Actions\RecurringReservations\GenerateFutureRecurringInstances;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Send daily reservation digest to admins at 7 AM
Schedule::command('reservations:daily-digest')->dailyAt('07:00');

// Schedule confirmation reminders to be sent daily at 9 AM
Schedule::call(fn () => SendReservationConfirmationReminders::run())->dailyAt('09:00');

// Schedule reservation reminders to be sent daily at 10 AM
Schedule::call(fn () => SendReservationReminders::run())->dailyAt('10:00');

// Schedule membership reminders to be sent daily at 11 AM
Schedule::call(fn () => SendMembershipReminders::run())->dailyAt('11:00');

// Schedule monthly credit allocation
Schedule::command('credits:allocate')->daily();

Schedule::command('cloudflare:reload')->daily();

// Generate future instances for recurring reservations daily
Schedule::call(function () {
    GenerateFutureRecurringInstances::run();
})->daily()->at('00:00');

// Auto-cancel unconfirmed reservations daily
// Schedule::command('reservations:auto-cancel')->dailyAt('08:00');

// Expire credits daily
Schedule::command('credits:expire')->dailyAt('01:00');
