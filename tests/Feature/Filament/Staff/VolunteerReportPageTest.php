<?php

use App\Filament\Staff\Pages\VolunteerReportPage;
use App\Models\User;
use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Models\Position;
use CorvMC\Volunteering\Models\Shift;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->member = User::factory()->create();
    $this->member->assignRole('member');

    $this->position = Position::factory()->create(['title' => 'Sound Engineer']);
});

it('is accessible by users with volunteer.hours.report permission', function () {
    $this->actingAs($this->admin);

    expect(VolunteerReportPage::canAccess())->toBeTrue();
});

it('is not accessible by regular members', function () {
    $this->actingAs($this->member);

    expect(VolunteerReportPage::canAccess())->toBeFalse();
});

it('only counts CheckedOut and Approved hour logs', function () {
    $volunteer = User::factory()->create();
    $shift = Shift::factory()->for($this->position, 'position')->create();

    // Countable: CheckedOut (shift-based)
    HourLog::factory()
        ->for($volunteer, 'user')
        ->forShift($shift)
        ->checkedOut()
        ->create([
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHour(),
        ]);

    // Countable: Approved (self-reported)
    HourLog::factory()
        ->for($volunteer, 'user')
        ->for($this->position, 'position')
        ->approved()
        ->create([
            'started_at' => now()->subHours(5),
            'ended_at' => now()->subHours(3),
        ]);

    // Not countable: Pending
    HourLog::factory()
        ->for($volunteer, 'user')
        ->for($this->position, 'position')
        ->pending()
        ->create([
            'started_at' => now()->subHours(8),
            'ended_at' => now()->subHours(6),
        ]);

    // Not countable: Interested (shift-based)
    HourLog::factory()
        ->for($volunteer, 'user')
        ->forShift($shift)
        ->interested()
        ->create();

    $component = Livewire::actingAs($this->admin)
        ->test(VolunteerReportPage::class);

    $stats = $component->instance()->getStats();

    // 2 hours + 2 hours = 4 hours from the two countable logs
    expect($stats['total_hours'])->toBe(4.0)
        ->and($stats['unique_volunteers'])->toBe(1)
        ->and($stats['shifts_staffed'])->toBe(1);
});

it('filters by date range', function () {
    $volunteer = User::factory()->create();

    $insideDate = now()->subDays(10);
    $outsideDate = now()->subDays(60);

    // Inside range
    HourLog::factory()
        ->for($volunteer, 'user')
        ->for($this->position, 'position')
        ->state([
            'shift_id' => null,
            'status' => \CorvMC\Volunteering\States\HourLogState\Approved::class,
            'reviewed_by' => User::factory(),
            'started_at' => $insideDate->copy()->startOfDay(),
            'ended_at' => $insideDate->copy()->startOfDay()->addHours(2),
        ])
        ->create();

    // Outside range
    HourLog::factory()
        ->for($volunteer, 'user')
        ->for($this->position, 'position')
        ->state([
            'shift_id' => null,
            'status' => \CorvMC\Volunteering\States\HourLogState\Approved::class,
            'reviewed_by' => User::factory(),
            'started_at' => $outsideDate->copy()->startOfDay(),
            'ended_at' => $outsideDate->copy()->startOfDay()->addHours(3),
        ])
        ->create();

    $component = Livewire::actingAs($this->admin)
        ->test(VolunteerReportPage::class)
        ->set('start_date', now()->subDays(30)->toDateString())
        ->set('end_date', now()->toDateString());

    $stats = $component->instance()->getStats();

    // Only the inside-range log (2 hours)
    expect($stats['total_hours'])->toBe(2.0)
        ->and($stats['unique_volunteers'])->toBe(1);
});

it('filters by tags', function () {
    $volunteer = User::factory()->create();

    $tagged = HourLog::factory()
        ->for($volunteer, 'user')
        ->for($this->position, 'position')
        ->approved()
        ->create([
            'started_at' => now()->subHours(4),
            'ended_at' => now()->subHours(2),
        ]);
    $tagged->attachTag('community-service');

    $untagged = HourLog::factory()
        ->for($volunteer, 'user')
        ->for($this->position, 'position')
        ->approved()
        ->create([
            'started_at' => now()->subHours(6),
            'ended_at' => now()->subHours(4),
        ]);

    $component = Livewire::actingAs($this->admin)
        ->test(VolunteerReportPage::class)
        ->set('tag_filter', 'community-service');

    $stats = $component->instance()->getStats();

    // Only the tagged log (2 hours)
    expect($stats['total_hours'])->toBe(2.0);
});

it('groups hours by volunteer correctly', function () {
    $volunteer1 = User::factory()->create(['name' => 'Alice']);
    $volunteer2 = User::factory()->create(['name' => 'Bob']);

    // Alice: 3 hours
    HourLog::factory()
        ->for($volunteer1, 'user')
        ->for($this->position, 'position')
        ->approved()
        ->create([
            'started_at' => now()->subHours(4),
            'ended_at' => now()->subHour(),
        ]);

    // Bob: 2 hours across 2 sessions
    HourLog::factory()
        ->for($volunteer2, 'user')
        ->for($this->position, 'position')
        ->approved()
        ->count(2)
        ->create([
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHour(),
        ]);

    $component = Livewire::actingAs($this->admin)
        ->test(VolunteerReportPage::class);

    $byVolunteer = $component->instance()->getHoursByVolunteer();

    expect($byVolunteer)->toHaveCount(2);

    $alice = $byVolunteer->firstWhere('name', 'Alice');
    $bob = $byVolunteer->firstWhere('name', 'Bob');

    expect($alice['total_hours'])->toBe(3.0)
        ->and($alice['sessions'])->toBe(1)
        ->and($bob['total_hours'])->toBe(2.0)
        ->and($bob['sessions'])->toBe(2);
});

it('groups hours by position correctly', function () {
    $volunteer = User::factory()->create();
    $reviewer = User::factory()->create();
    $position2 = Position::factory()->create(['title' => 'Door Staff']);

    // 2 hours on Sound Engineer
    HourLog::factory()
        ->for($volunteer, 'user')
        ->for($this->position, 'position')
        ->state([
            'shift_id' => null,
            'status' => \CorvMC\Volunteering\States\HourLogState\Approved::class,
            'reviewed_by' => $reviewer->id,
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHour(),
        ])
        ->create();

    // 1 hour on Door Staff
    HourLog::factory()
        ->for($volunteer, 'user')
        ->for($position2, 'position')
        ->state([
            'shift_id' => null,
            'status' => \CorvMC\Volunteering\States\HourLogState\Approved::class,
            'reviewed_by' => $reviewer->id,
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHour(),
        ])
        ->create();

    $component = Livewire::actingAs($this->admin)
        ->test(VolunteerReportPage::class);

    $byPosition = $component->instance()->getHoursByPosition();

    expect($byPosition)->toHaveCount(2);

    $soundEng = $byPosition->firstWhere('title', 'Sound Engineer');
    $doorStaff = $byPosition->firstWhere('title', 'Door Staff');

    expect($soundEng['total_hours'])->toBe(2.0)
        ->and($soundEng['volunteer_count'])->toBe(1)
        ->and($doorStaff['total_hours'])->toBe(1.0)
        ->and($doorStaff['volunteer_count'])->toBe(1);
});
