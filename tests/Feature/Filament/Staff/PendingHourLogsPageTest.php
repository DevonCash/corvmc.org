<?php

use App\Filament\Staff\Pages\PendingHourLogsPage;
use App\Models\User;
use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Models\Position;
use CorvMC\Volunteering\States\HourLogState\Approved;
use CorvMC\Volunteering\States\HourLogState\Pending;
use CorvMC\Volunteering\States\HourLogState\Rejected;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->member = User::factory()->create();
    $this->member->assignRole('member');

    $this->position = Position::factory()->create(['title' => 'Grant Writer']);
});

it('is accessible by users with volunteer.hours.approve permission', function () {
    $this->actingAs($this->admin);

    expect(PendingHourLogsPage::canAccess())->toBeTrue();
});

it('is not accessible by regular members', function () {
    $this->actingAs($this->member);

    expect(PendingHourLogsPage::canAccess())->toBeFalse();
});

it('renders for authorized users', function () {
    HourLog::factory()
        ->for(User::factory(), 'user')
        ->for($this->position, 'position')
        ->pending()
        ->create();

    Livewire::actingAs($this->admin)
        ->test(PendingHourLogsPage::class)
        ->assertSuccessful();
});

it('table query only includes pending hour logs', function () {
    $volunteer = User::factory()->create();

    $pending = HourLog::factory()
        ->for($volunteer, 'user')
        ->for($this->position, 'position')
        ->pending()
        ->create();

    $approved = HourLog::factory()
        ->for($volunteer, 'user')
        ->for($this->position, 'position')
        ->approved()
        ->create();

    $component = Livewire::actingAs($this->admin)
        ->test(PendingHourLogsPage::class);

    // The table query should only have pending records
    $tableRecords = $component->instance()->getTableRecords();

    expect($tableRecords)->toHaveCount(1)
        ->and($tableRecords->first()->id)->toBe($pending->id);
});

it('approve action transitions hour log to approved', function () {
    $volunteer = User::factory()->create();

    $hourLog = HourLog::factory()
        ->for($volunteer, 'user')
        ->for($this->position, 'position')
        ->pending()
        ->create();

    Livewire::actingAs($this->admin)
        ->test(PendingHourLogsPage::class)
        ->callTableAction('approve', $hourLog);

    expect($hourLog->fresh()->status)->toBeInstanceOf(Approved::class)
        ->and($hourLog->fresh()->reviewed_by)->toBe($this->admin->id);
});

it('reject action transitions hour log to rejected with notes', function () {
    $volunteer = User::factory()->create();

    $hourLog = HourLog::factory()
        ->for($volunteer, 'user')
        ->for($this->position, 'position')
        ->pending()
        ->create();

    Livewire::actingAs($this->admin)
        ->test(PendingHourLogsPage::class)
        ->callTableAction('reject', $hourLog, data: [
            'notes' => 'Hours do not match event records.',
        ]);

    $fresh = $hourLog->fresh();
    expect($fresh->status)->toBeInstanceOf(Rejected::class)
        ->and($fresh->reviewed_by)->toBe($this->admin->id)
        ->and($fresh->notes)->toBe('Hours do not match event records.');
});
