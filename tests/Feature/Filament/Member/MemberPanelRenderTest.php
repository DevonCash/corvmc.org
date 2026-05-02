<?php

/**
 * Member Panel Render Smoke Tests
 *
 * Verifies every Member panel page mounts without throwing.
 * Minimal data preparation — if a page crashes on a near-empty
 * database, that's a bug this test is designed to catch.
 */

use App\Filament\Member\Pages\MemberDashboard;
use App\Filament\Member\Pages\MyAccount;
use App\Filament\Member\Pages\MyMembership;
use App\Filament\Member\Pages\MyOrders;
use App\Filament\Member\Pages\MyProfile;
use App\Filament\Member\Pages\MyTickets;
use App\Filament\Member\Pages\SubmitHoursPage;
use App\Filament\Member\Pages\VolunteerPage;
use App\Filament\Member\Resources\Bands\Pages\EditBand;
use App\Filament\Member\Resources\Bands\Pages\ListBands;
use App\Filament\Member\Resources\Bands\Pages\ViewBand;
use App\Filament\Member\Resources\Equipment\EquipmentLoans\Pages\ListEquipmentLoans;
use App\Filament\Member\Resources\Equipment\Pages\ListEquipment;
use App\Filament\Member\Resources\Equipment\Pages\ViewEquipment;
use App\Filament\Member\Resources\MemberProfiles\Pages\ListMemberProfiles;
use App\Filament\Member\Resources\MemberProfiles\Pages\ViewMemberProfile;
use App\Filament\Member\Resources\Reservations\Pages\ListReservations;
use App\Filament\Member\Resources\Reservations\Pages\ViewReservation;
use App\Models\User;
use CorvMC\Bands\Models\Band;
use CorvMC\Equipment\Models\Equipment;
use CorvMC\Membership\Models\MemberProfile;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\States\ReservationState\Confirmed;
use Livewire\Livewire;

uses()->group('smoke', 'member-panel');

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $this->member = User::factory()->create();
    $this->member->assignRole('member');
});

describe('Custom pages', function () {
    it('renders MemberDashboard', function () {
        Livewire::actingAs($this->member)
            ->test(MemberDashboard::class)
            ->assertSuccessful();
    });

    it('renders MyAccount', function () {
        Livewire::actingAs($this->member)
            ->test(MyAccount::class)
            ->assertSuccessful();
    });

    it('renders MyProfile', function () {
        Livewire::actingAs($this->member)
            ->test(MyProfile::class)
            ->assertSuccessful();
    });

    it('renders MyMembership', function () {
        Livewire::actingAs($this->member)
            ->test(MyMembership::class)
            ->assertSuccessful();
    });

    it('renders MyOrders', function () {
        Livewire::actingAs($this->member)
            ->test(MyOrders::class)
            ->assertSuccessful();
    });

    it('renders MyTickets', function () {
        Livewire::actingAs($this->member)
            ->test(MyTickets::class)
            ->assertSuccessful();
    });

    it('renders VolunteerPage', function () {
        Livewire::actingAs($this->member)
            ->test(VolunteerPage::class)
            ->assertSuccessful();
    });

    it('renders SubmitHoursPage', function () {
        Livewire::actingAs($this->member)
            ->test(SubmitHoursPage::class)
            ->assertSuccessful();
    });
});

describe('Bands resource', function () {
    it('renders ListBands', function () {
        Livewire::actingAs($this->member)
            ->test(ListBands::class)
            ->assertSuccessful();
    });

    it('renders ViewBand', function () {
        $band = Band::create([
            'name' => 'Test Band',
            'slug' => 'test-band',
            'owner_id' => $this->member->id,
        ]);
        $band->members()->attach($this->member);

        Livewire::actingAs($this->member)
            ->test(ViewBand::class, ['record' => $band->getRouteKey()])
            ->assertSuccessful();
    });

    it('renders EditBand', function () {
        $band = Band::create([
            'name' => 'Test Band',
            'slug' => 'test-band',
            'owner_id' => $this->member->id,
        ]);
        $band->members()->attach($this->member);

        Livewire::actingAs($this->member)
            ->test(EditBand::class, ['record' => $band->getRouteKey()])
            ->assertSuccessful();
    });
});

describe('Reservations resource', function () {
    it('renders ListReservations', function () {
        Livewire::actingAs($this->member)
            ->test(ListReservations::class)
            ->assertSuccessful();
    });

    it('renders ViewReservation', function () {
        $reservation = RehearsalReservation::create([
            'user_id' => $this->member->id,
            'reserved_at' => now()->addDays(3)->setHour(10),
            'reserved_until' => now()->addDays(3)->setHour(12),
            'hours_used' => 2,
            'status' => Confirmed::class,
        ]);

        Livewire::actingAs($this->member)
            ->test(ViewReservation::class, ['record' => $reservation->getRouteKey()])
            ->assertSuccessful();
    });
});

describe('Equipment resource', function () {
    it('renders ListEquipment', function () {
        Livewire::actingAs($this->member)
            ->test(ListEquipment::class)
            ->assertSuccessful();
    });

    it('renders ViewEquipment', function () {
        $equipment = Equipment::create([
            'name' => 'Test Amp',
            'type' => 'amplifier',
            'description' => 'A test amplifier',
        ]);

        Livewire::actingAs($this->member)
            ->test(ViewEquipment::class, ['record' => $equipment->getRouteKey()])
            ->assertSuccessful();
    });

    it('renders ListEquipmentLoans', function () {
        Livewire::actingAs($this->member)
            ->test(ListEquipmentLoans::class)
            ->assertSuccessful();
    });
});

describe('Member Profiles resource', function () {
    it('renders ListMemberProfiles', function () {
        Livewire::actingAs($this->member)
            ->test(ListMemberProfiles::class)
            ->assertSuccessful();
    });

    it('renders ViewMemberProfile', function () {
        $profile = MemberProfile::firstOrCreate(['user_id' => $this->member->id]);

        Livewire::actingAs($this->member)
            ->test(ViewMemberProfile::class, ['record' => $profile->getRouteKey()])
            ->assertSuccessful();
    });
});
