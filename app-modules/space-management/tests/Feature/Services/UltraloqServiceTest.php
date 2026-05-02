<?php

use App\Models\User;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Services\UltraloqService;
use CorvMC\SpaceManagement\Settings\UltraloqSettings;

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Jane Doe']);
    $this->user->assignRole('sustaining member');

    $this->settings = app(UltraloqSettings::class);
});

// =========================================================================
// Code Generation
// =========================================================================

it('generates a 6-digit numeric code', function () {
    $service = app(UltraloqService::class);

    $code = $service->generateCode();

    expect($code)
        ->toHaveLength(6)
        ->toMatch('/^\d{6}$/');
});

it('generates unique codes', function () {
    $service = app(UltraloqService::class);

    $codes = collect(range(1, 50))->map(fn () => $service->generateCode());

    // With 6-digit codes and only 50 samples, collisions are extremely unlikely
    expect($codes->unique()->count())->toBe(50);
});

// =========================================================================
// Schedule Building
// =========================================================================

it('builds schedule with 15 min early access and 30 min late access', function () {
    $reservation = RehearsalReservation::factory()->create([
        'reservable_type' => 'user',
        'reservable_id' => $this->user->id,
        'reserved_at' => '2026-05-10 14:00:00',
        'reserved_until' => '2026-05-10 16:00:00',
    ]);

    $service = app(UltraloqService::class);
    $schedule = $service->buildSchedule($reservation);

    // Start: 14:00 - 15 min = 13:45
    // End: 16:00 + 30 min = 16:30
    expect($schedule['daterange'])->toBe(['2026-05-10 13:45', '2026-05-10 16:30']);
    expect($schedule['timerange'])->toBe(['13:45', '16:30']);
    expect($schedule['weeks'])->toBe([0, 1, 2, 3, 4, 5, 6]);
});

it('handles reservations near midnight', function () {
    $reservation = RehearsalReservation::factory()->make([
        'reservable_type' => 'user',
        'reservable_id' => $this->user->id,
        'reserved_at' => '2026-05-10 23:00:00',
        'reserved_until' => '2026-05-10 23:30:00',
    ]);

    $service = app(UltraloqService::class);
    $schedule = $service->buildSchedule($reservation);

    // Start: 23:00 - 15 min = 22:45
    // End: 23:30 + 30 min = 00:00 next day
    expect($schedule['daterange'][0])->toBe('2026-05-10 22:45');
    expect($schedule['daterange'][1])->toBe('2026-05-11 00:00');
});

// =========================================================================
// SMS Message Composition
// =========================================================================

it('composes SMS message with code and access window', function () {
    $reservation = RehearsalReservation::factory()->create([
        'reservable_type' => 'user',
        'reservable_id' => $this->user->id,
        'reserved_at' => '2026-05-10 14:00:00',
        'reserved_until' => '2026-05-10 16:00:00',
        'lock_code' => '123456',
    ]);

    $message = UltraloqService::composeSmsMessage($reservation);

    expect($message)
        ->toContain('123456')
        ->toContain('1:45 PM')    // 14:00 - 15 min
        ->toContain('4:30 PM')    // 16:00 + 30 min
        ->toContain('May 10');
});

it('returns null for SMS when no lock code', function () {
    $reservation = RehearsalReservation::factory()->create([
        'reservable_type' => 'user',
        'reservable_id' => $this->user->id,
        'reserved_at' => '2026-05-10 14:00:00',
        'reserved_until' => '2026-05-10 16:00:00',
        'lock_code' => null,
    ]);

    expect(UltraloqService::composeSmsMessage($reservation))->toBeNull();
});

// =========================================================================
// createTemporaryUser (without API)
// =========================================================================

it('skips lock code creation when not configured', function () {
    $reservation = RehearsalReservation::factory()->create([
        'reservable_type' => 'user',
        'reservable_id' => $this->user->id,
        'reserved_at' => '2026-05-10 14:00:00',
        'reserved_until' => '2026-05-10 16:00:00',
    ]);

    $service = app(UltraloqService::class);
    $result = $service->createTemporaryUser($reservation);

    expect($result)->toBeNull();
    expect($reservation->fresh()->lock_code)->toBeNull();
});

// =========================================================================
// Settings
// =========================================================================

it('reports not configured when tokens are empty', function () {
    expect($this->settings->isConfigured())->toBeFalse();
    expect($this->settings->isConnected())->toBeFalse();
    expect($this->settings->hasDevice())->toBeFalse();
});

it('reports configured when tokens and device are set', function () {
    $this->settings->access_token = 'test-token';
    $this->settings->device_id = 'AA:BB:CC:DD:EE:FF';
    $this->settings->save();

    $fresh = app(UltraloqSettings::class);

    expect($fresh->isConfigured())->toBeTrue();
    expect($fresh->isConnected())->toBeTrue();
    expect($fresh->hasDevice())->toBeTrue();
});
