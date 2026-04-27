<?php

use App\Models\User;
use CorvMC\Events\Events\EventCancelled;
use CorvMC\Events\Models\Event;
use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Models\Position;
use CorvMC\Volunteering\Models\Shift;
use CorvMC\Volunteering\Notifications\ShiftReleasedNotification;
use CorvMC\Volunteering\States\HourLogState\CheckedOut;
use CorvMC\Volunteering\States\HourLogState\Confirmed;
use CorvMC\Volunteering\States\HourLogState\Interested;
use CorvMC\Volunteering\States\HourLogState\Released;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    Notification::fake();

    $this->position = Position::factory()->create(['title' => 'Sound Engineer']);

    $this->event = Event::factory()->create(['status' => \CorvMC\Events\Enums\EventStatus::Scheduled]);

    $this->shift = Shift::factory()
        ->for($this->position, 'position')
        ->create([
            'event_id' => $this->event->id,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(3),
            'capacity' => 5,
        ]);
});

it('releases all active volunteers when an event is cancelled', function () {
    $volunteer1 = User::factory()->create();
    $volunteer2 = User::factory()->create();

    $log1 = HourLog::factory()
        ->for($volunteer1, 'user')
        ->forShift($this->shift)
        ->interested()
        ->create();

    $log2 = HourLog::factory()
        ->for($volunteer2, 'user')
        ->forShift($this->shift)
        ->confirmed()
        ->create();

    EventCancelled::dispatch($this->event);

    expect($log1->fresh()->status)->toBeInstanceOf(Released::class)
        ->and($log2->fresh()->status)->toBeInstanceOf(Released::class);
});

it('sends release notifications to affected volunteers', function () {
    $volunteer = User::factory()->create();

    HourLog::factory()
        ->for($volunteer, 'user')
        ->forShift($this->shift)
        ->confirmed()
        ->create();

    EventCancelled::dispatch($this->event);

    Notification::assertSentTo($volunteer, ShiftReleasedNotification::class);
});

it('does not affect terminal-status hour logs', function () {
    $volunteer = User::factory()->create();

    $log = HourLog::factory()
        ->for($volunteer, 'user')
        ->forShift($this->shift)
        ->checkedOut()
        ->create();

    EventCancelled::dispatch($this->event);

    expect($log->fresh()->status)->toBeInstanceOf(CheckedOut::class);
});

it('does not affect volunteers on other events', function () {
    $otherEvent = Event::factory()->create(['status' => \CorvMC\Events\Enums\EventStatus::Scheduled]);
    $otherShift = Shift::factory()
        ->for($this->position, 'position')
        ->create([
            'event_id' => $otherEvent->id,
            'start_at' => now()->addDays(2),
            'end_at' => now()->addDays(2)->addHours(3),
            'capacity' => 2,
        ]);

    $volunteer = User::factory()->create();

    $log = HourLog::factory()
        ->for($volunteer, 'user')
        ->forShift($otherShift)
        ->confirmed()
        ->create();

    EventCancelled::dispatch($this->event);

    expect($log->fresh()->status)->toBeInstanceOf(Confirmed::class);
});

it('handles events with no shifts gracefully', function () {
    $emptyEvent = Event::factory()->create(['status' => \CorvMC\Events\Enums\EventStatus::Scheduled]);

    EventCancelled::dispatch($emptyEvent);

    // No exception — nothing to release
    expect(true)->toBeTrue();
});
