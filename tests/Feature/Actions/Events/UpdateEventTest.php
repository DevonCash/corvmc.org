<?php

use CorvMC\Events\Actions\UpdateEvent;
use CorvMC\Events\Models\Event;
use Carbon\Carbon;

it('persists time changes when editing an event', function () {
    // Create an event with a specific datetime
    $event = Event::factory()->create([
        'start_datetime' => Carbon::parse('2024-12-15 14:00:00', config('app.timezone')),
        'end_datetime' => Carbon::parse('2024-12-15 16:00:00', config('app.timezone')),
    ]);

    // Verify initial state
    expect($event->start_datetime->format('Y-m-d H:i'))->toBe('2024-12-15 14:00')
        ->and($event->end_datetime->format('Y-m-d H:i'))->toBe('2024-12-15 16:00');

    // Simulate editing the event through the form (changing only the time)
    // This simulates what Filament sends when a user changes the time fields
    $updatedData = [
        'event_date' => '2024-12-15',
        'start_time' => '15:00',  // Changed from 14:00 to 15:00
        'end_time' => '17:00',    // Changed from 16:00 to 17:00
    ];

    // Update the event using the UpdateEvent action
    $updatedEvent = UpdateEvent::run($event, $updatedData);

    // Reload from database to ensure persistence
    $updatedEvent->refresh();

    // Assert that the time changes persisted to the database
    expect($updatedEvent->start_datetime->format('Y-m-d H:i'))->toBe('2024-12-15 15:00')
        ->and($updatedEvent->end_datetime->format('Y-m-d H:i'))->toBe('2024-12-15 17:00');
});

it('persists date and time changes when editing an event', function () {
    // Create an event with a specific datetime
    $event = Event::factory()->create([
        'start_datetime' => Carbon::parse('2024-12-15 14:00:00', config('app.timezone')),
        'end_datetime' => Carbon::parse('2024-12-15 16:00:00', config('app.timezone')),
    ]);

    // Simulate editing both date and time
    $updatedData = [
        'event_date' => '2024-12-20',  // Changed date
        'start_time' => '18:00',       // Changed time
        'end_time' => '20:00',         // Changed time
    ];

    // Update the event
    $updatedEvent = UpdateEvent::run($event, $updatedData);
    $updatedEvent->refresh();

    // Assert that both date and time changes persisted
    expect($updatedEvent->start_datetime->format('Y-m-d H:i'))->toBe('2024-12-20 18:00')
        ->and($updatedEvent->end_datetime->format('Y-m-d H:i'))->toBe('2024-12-20 20:00');
});

it('handles doors_time updates correctly', function () {
    // Create an event with doors time
    $event = Event::factory()->create([
        'start_datetime' => Carbon::parse('2024-12-15 20:00:00', config('app.timezone')),
        'doors_datetime' => Carbon::parse('2024-12-15 19:00:00', config('app.timezone')),
    ]);

    // Update only the doors time
    $updatedData = [
        'event_date' => '2024-12-15',
        'doors_time' => '18:30',  // Changed from 19:00 to 18:30
        'start_time' => '20:00',  // Keep the same
    ];

    $updatedEvent = UpdateEvent::run($event, $updatedData);
    $updatedEvent->refresh();

    // Assert doors time was updated
    expect($updatedEvent->doors_datetime->format('Y-m-d H:i'))->toBe('2024-12-15 18:30');
});

it('preserves existing datetime when virtual fields are not provided', function () {
    // Create an event
    $event = Event::factory()->create([
        'start_datetime' => Carbon::parse('2024-12-15 14:00:00', config('app.timezone')),
        'end_datetime' => Carbon::parse('2024-12-15 16:00:00', config('app.timezone')),
        'title' => 'Original Title',
    ]);

    // Update only the title (no date/time fields)
    $updatedData = [
        'title' => 'Updated Title',
    ];

    $updatedEvent = UpdateEvent::run($event, $updatedData);
    $updatedEvent->refresh();

    // Assert datetime was preserved
    expect($updatedEvent->title)->toBe('Updated Title')
        ->and($updatedEvent->start_datetime->format('Y-m-d H:i'))->toBe('2024-12-15 14:00')
        ->and($updatedEvent->end_datetime->format('Y-m-d H:i'))->toBe('2024-12-15 16:00');
});
