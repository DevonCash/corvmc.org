<?php

use App\Facades\NotificationService;
use CorvMC\Support\Facades\RecurringService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Send daily reservation digest to admins at 8 PM the night before
Schedule::command('reservations:daily-digest')->dailyAt('20:00');

// Schedule confirmation reminders to be sent daily at 9 AM
Schedule::call(fn() => NotificationService::sendConfirmationReminders())->dailyAt('09:00');

// Schedule reservation reminders to be sent daily at 10 AM
Schedule::call(fn() => NotificationService::sendReservationReminders())->dailyAt('10:00');

// Schedule membership reminders to be sent daily at 11 AM
Schedule::call(fn() => NotificationService::sendMembershipReminders())->dailyAt('11:00');

// Schedule monthly credit allocation
Schedule::command('credits:allocate')->daily();

Schedule::command('cloudflare:reload')->daily();

// Generate future instances for recurring reservations daily
Schedule::call(function () {
    RecurringService::generateFutureInstances();
})->daily()->at('00:00');


// Expire credits daily
Schedule::command('credits:expire')->dailyAt('01:00');

// Sweep stale Stripe transactions (expired/abandoned checkouts) every hour
Schedule::command('finance:sweep-stale')->hourly();

// Nightly reconciliation: verify Stripe settlements + archive old webhook events
Schedule::command('finance:reconcile')->dailyAt('03:00');
