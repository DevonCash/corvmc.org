<?php

use App\Models\User;
use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Models\Position;
use CorvMC\Volunteering\Models\Shift;
use CorvMC\Volunteering\Notifications\ShiftReminderNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    Notification::fake();
    Cache::flush();

    $this->volunteer = User::factory()->create();
    $this->volunteer->assignRole('member');

    $this->position = Position::factory()->create(['title' => 'Sound Engineer']);
});

it('sends reminders for shifts starting in 23-25 hours', function () {
    $shift = Shift::factory()
        ->for($this->position, 'position')
        ->create([
            'start_at' => now()->addHours(24),
            'end_at' => now()->addHours(27),
            'capacity' => 2,
        ]);

    HourLog::factory()
        ->for($this->volunteer, 'user')
        ->forShift($shift)
        ->confirmed()
        ->create();

    $this->artisan('volunteering:send-shift-reminders')
        ->assertSuccessful();

    Notification::assertSentTo($this->volunteer, ShiftReminderNotification::class);
});

it('does not send reminders for shifts outside the 23-25h window', function () {
    // Shift in 48 hours — too far out
    $shift = Shift::factory()
        ->for($this->position, 'position')
        ->create([
            'start_at' => now()->addHours(48),
            'end_at' => now()->addHours(51),
            'capacity' => 2,
        ]);

    HourLog::factory()
        ->for($this->volunteer, 'user')
        ->forShift($shift)
        ->confirmed()
        ->create();

    $this->artisan('volunteering:send-shift-reminders')
        ->assertSuccessful();

    Notification::assertNotSentTo($this->volunteer, ShiftReminderNotification::class);
});

it('does not send reminders for non-confirmed volunteers', function () {
    $shift = Shift::factory()
        ->for($this->position, 'position')
        ->create([
            'start_at' => now()->addHours(24),
            'end_at' => now()->addHours(27),
            'capacity' => 2,
        ]);

    // Interested, not yet confirmed
    HourLog::factory()
        ->for($this->volunteer, 'user')
        ->forShift($shift)
        ->interested()
        ->create();

    $this->artisan('volunteering:send-shift-reminders')
        ->assertSuccessful();

    Notification::assertNotSentTo($this->volunteer, ShiftReminderNotification::class);
});

it('does not send duplicate reminders on repeated runs', function () {
    $shift = Shift::factory()
        ->for($this->position, 'position')
        ->create([
            'start_at' => now()->addHours(24),
            'end_at' => now()->addHours(27),
            'capacity' => 2,
        ]);

    HourLog::factory()
        ->for($this->volunteer, 'user')
        ->forShift($shift)
        ->confirmed()
        ->create();

    // Run twice
    $this->artisan('volunteering:send-shift-reminders')->assertSuccessful();
    $this->artisan('volunteering:send-shift-reminders')->assertSuccessful();

    Notification::assertSentToTimes($this->volunteer, ShiftReminderNotification::class, 1);
});
