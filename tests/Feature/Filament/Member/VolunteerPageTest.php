<?php

use App\Filament\Member\Pages\VolunteerPage;
use App\Models\User;
use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Models\Position;
use CorvMC\Volunteering\Models\Shift;
use CorvMC\Volunteering\States\HourLogState\CheckedIn;
use CorvMC\Volunteering\States\HourLogState\CheckedOut;
use CorvMC\Volunteering\States\HourLogState\Confirmed;
use CorvMC\Volunteering\States\HourLogState\Interested;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $this->member = User::factory()->create();
    $this->member->assignRole('member');

    $this->position = Position::factory()->create(['title' => 'Sound Engineer']);
});

// =========================================================================
// Access control
// =========================================================================

it('is accessible by members with volunteer.signup permission', function () {
    $this->actingAs($this->member);

    expect(VolunteerPage::canAccess())->toBeTrue();
});

it('renders for authorized members', function () {
    Livewire::actingAs($this->member)
        ->test(VolunteerPage::class)
        ->assertSuccessful();
});

// =========================================================================
// Sign-up
// =========================================================================

it('shows open shifts with available capacity', function () {
    $shift = Shift::factory()
        ->for($this->position, 'position')
        ->create([
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(3),
            'capacity' => 2,
        ]);

    $component = Livewire::actingAs($this->member)
        ->test(VolunteerPage::class);

    $openShifts = $component->instance()->getOpenShifts()->flatten(1);

    expect($openShifts)->toHaveCount(1)
        ->and($openShifts->first()['can_sign_up'])->toBeTrue()
        ->and($openShifts->first()['available'])->toBe(2);
});

it('can sign up for a shift', function () {
    $shift = Shift::factory()
        ->for($this->position, 'position')
        ->create([
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(3),
            'capacity' => 2,
        ]);

    Livewire::actingAs($this->member)
        ->test(VolunteerPage::class)
        ->call('signUp', $shift->id);

    expect(HourLog::where('user_id', $this->member->id)->where('shift_id', $shift->id)->exists())
        ->toBeTrue();
});

it('shows status badge instead of sign-up button when already signed up', function () {
    $shift = Shift::factory()
        ->for($this->position, 'position')
        ->create([
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(3),
            'capacity' => 2,
        ]);

    HourLog::factory()
        ->for($this->member, 'user')
        ->forShift($shift)
        ->interested()
        ->create();

    $component = Livewire::actingAs($this->member)
        ->test(VolunteerPage::class);

    $openShifts = $component->instance()->getOpenShifts()->flatten(1);
    $item = $openShifts->first();

    expect($item['can_sign_up'])->toBeFalse()
        ->and($item['my_hour_log'])->not->toBeNull()
        ->and($item['my_hour_log']->status)->toBeInstanceOf(Interested::class);
});

it('does not show full shifts in open shifts', function () {
    $shift = Shift::factory()
        ->for($this->position, 'position')
        ->create([
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(3),
            'capacity' => 1,
        ]);

    // Fill the shift
    HourLog::factory()
        ->for(User::factory(), 'user')
        ->forShift($shift)
        ->interested()
        ->create();

    $component = Livewire::actingAs($this->member)
        ->test(VolunteerPage::class);

    $openShifts = $component->instance()->getOpenShifts()->flatten(1);

    expect($openShifts)->toHaveCount(0);
});

// =========================================================================
// Self-check-in/out
// =========================================================================

it('allows self-check-in within 30 minutes of shift start', function () {
    $shift = Shift::factory()
        ->for($this->position, 'position')
        ->create([
            'start_at' => now()->addMinutes(15),
            'end_at' => now()->addHours(3),
            'capacity' => 2,
        ]);

    $hourLog = HourLog::factory()
        ->for($this->member, 'user')
        ->forShift($shift)
        ->confirmed()
        ->create();

    expect(VolunteerPage::isInCheckInWindow($shift))->toBeTrue();

    Livewire::actingAs($this->member)
        ->test(VolunteerPage::class)
        ->call('checkIn', $hourLog->id);

    expect($hourLog->fresh()->status)->toBeInstanceOf(CheckedIn::class);
});

it('allows self-check-out when checked in', function () {
    $shift = Shift::factory()
        ->for($this->position, 'position')
        ->create([
            'start_at' => now()->subHour(),
            'end_at' => now()->addHours(2),
            'capacity' => 2,
        ]);

    $hourLog = HourLog::factory()
        ->for($this->member, 'user')
        ->forShift($shift)
        ->checkedIn()
        ->create();

    Livewire::actingAs($this->member)
        ->test(VolunteerPage::class)
        ->call('checkOut', $hourLog->id);

    expect($hourLog->fresh()->status)->toBeInstanceOf(CheckedOut::class);
});

it('does not show check-in button outside the window', function () {
    $shift = Shift::factory()
        ->for($this->position, 'position')
        ->create([
            'start_at' => now()->addHours(2),
            'end_at' => now()->addHours(5),
            'capacity' => 2,
        ]);

    expect(VolunteerPage::isInCheckInWindow($shift))->toBeFalse();
});

it('does not show check-in button after shift ends', function () {
    $shift = Shift::factory()
        ->for($this->position, 'position')
        ->create([
            'start_at' => now()->subHours(4),
            'end_at' => now()->subHour(),
            'capacity' => 2,
        ]);

    expect(VolunteerPage::isInCheckInWindow($shift))->toBeFalse();
});
