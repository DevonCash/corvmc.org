<?php

use App\Filament\Member\Pages\SubmitHoursPage;
use App\Models\User;
use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Models\Position;
use CorvMC\Volunteering\States\HourLogState\Pending;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $this->member = User::factory()->create();
    $this->member->assignRole('member');

    $this->position = Position::factory()->create(['title' => 'Grant Writer']);
});

it('is accessible by members with volunteer.hours.submit permission', function () {
    $this->actingAs($this->member);

    expect(SubmitHoursPage::canAccess())->toBeTrue();
});

it('renders for authorized members', function () {
    Livewire::actingAs($this->member)
        ->test(SubmitHoursPage::class)
        ->assertSuccessful();
});

it('submits self-reported hours successfully', function () {
    Livewire::actingAs($this->member)
        ->test(SubmitHoursPage::class)
        ->fillForm([
            'position_id' => $this->position->id,
            'started_at' => now()->subHours(4)->toDateTimeString(),
            'ended_at' => now()->subHours(2)->toDateTimeString(),
            'notes' => 'Helped with grant paperwork',
        ])
        ->call('submit')
        ->assertHasNoFormErrors();

    $log = HourLog::where('user_id', $this->member->id)
        ->where('position_id', $this->position->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBeInstanceOf(Pending::class)
        ->and($log->notes)->toBe('Helped with grant paperwork');
});

it('shows submission history', function () {
    HourLog::factory()
        ->for($this->member, 'user')
        ->for($this->position, 'position')
        ->pending()
        ->create();

    $component = Livewire::actingAs($this->member)
        ->test(SubmitHoursPage::class);

    $submissions = $component->instance()->getSubmissions();

    expect($submissions)->toHaveCount(1);
});

it('validates required fields', function () {
    Livewire::actingAs($this->member)
        ->test(SubmitHoursPage::class)
        ->fillForm([
            'position_id' => null,
            'started_at' => null,
            'ended_at' => null,
        ])
        ->call('submit')
        ->assertHasFormErrors(['position_id', 'started_at', 'ended_at']);
});
