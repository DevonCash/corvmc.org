<?php

use App\Models\User;
use CorvMC\Sponsorship\Facades\SponsorshipService;
use CorvMC\Sponsorship\Models\Sponsor;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

describe('Sponsorship Workflow: Assign Sponsorship', function () {
    it('assigns a user to a sponsor', function () {
        $sponsor = Sponsor::factory()->melody()->active()->create();
        $user = User::factory()->create();

        // Melody tier has 10 slots
        expect($sponsor->sponsored_memberships)->toBe(10);
        expect($sponsor->availableSlots())->toBe(10);

        SponsorshipService::assignMembership($sponsor, $user);

        expect($sponsor->sponsoredMembers()->count())->toBe(1);
        expect($sponsor->usedSlots())->toBe(1);
        expect($sponsor->availableSlots())->toBe(9);
    });

    it('assigns multiple users to a sponsor', function () {
        $sponsor = Sponsor::factory()->harmony()->active()->create();
        $users = User::factory()->count(3)->create();

        // Harmony tier has 5 slots
        expect($sponsor->sponsored_memberships)->toBe(5);

        foreach ($users as $user) {
            SponsorshipService::assignMembership($sponsor, $user);
        }

        expect($sponsor->sponsoredMembers()->count())->toBe(3);
        expect($sponsor->availableSlots())->toBe(2);
    });

    it('prevents assigning same user twice', function () {
        $sponsor = Sponsor::factory()->melody()->active()->create();
        $user = User::factory()->create();

        SponsorshipService::assignMembership($sponsor, $user);

        expect(fn() => SponsorshipService::assignMembership($sponsor, $user))
            ->toThrow(\Exception::class, 'is already sponsored by');
    });

    it('prevents assignment when no slots available', function () {
        $sponsor = Sponsor::factory()->harmony()->active()->create();
        // Harmony tier has 5 slots
        $users = User::factory()->count(5)->create();

        // Fill all slots
        foreach ($users as $user) {
            SponsorshipService::assignMembership($sponsor, $user);
        }

        expect($sponsor->availableSlots())->toBe(0);
        expect($sponsor->hasAvailableSlots())->toBeFalse();

        // Try to assign one more
        $extraUser = User::factory()->create();
        expect(fn() => SponsorshipService::assignMembership($sponsor, $extraUser))
            ->toThrow(\Exception::class, 'has no available slots');
    });
});

describe('Sponsorship Workflow: Revoke Sponsorship', function () {
    it('revokes a sponsorship and frees up slot', function () {
        $sponsor = Sponsor::factory()->melody()->active()->create();
        $user = User::factory()->create();

        SponsorshipService::assignMembership($sponsor, $user);
        expect($sponsor->usedSlots())->toBe(1);

        SponsorshipService::revokeMembership($sponsor, $user);

        expect($sponsor->sponsoredMembers()->count())->toBe(0);
        expect($sponsor->usedSlots())->toBe(0);
        expect($sponsor->availableSlots())->toBe(10);
    });

    it('prevents revoking non-existent sponsorship', function () {
        $sponsor = Sponsor::factory()->melody()->active()->create();
        $user = User::factory()->create();

        expect(fn() => SponsorshipService::revokeMembership($sponsor, $user))
            ->toThrow(\Exception::class, 'is not sponsored by');
    });

    it('allows re-assignment after revocation', function () {
        $sponsor = Sponsor::factory()->harmony()->active()->create();
        $user = User::factory()->create();

        // Assign
        SponsorshipService::assignMembership($sponsor, $user);
        expect($sponsor->sponsoredMembers()->pluck('user_id'))->toContain($user->id);

        // Revoke
        SponsorshipService::revokeMembership($sponsor, $user);
        expect($sponsor->sponsoredMembers()->pluck('user_id'))->not->toContain($user->id);

        // Re-assign
        SponsorshipService::assignMembership($sponsor, $user);
        expect($sponsor->sponsoredMembers()->pluck('user_id'))->toContain($user->id);
    });
});

describe('Sponsorship Workflow: Slot Availability', function () {
    it('calculates correct slots for different tiers', function () {
        $harmonyTier = Sponsor::factory()->harmony()->active()->create();
        $melodyTier = Sponsor::factory()->melody()->active()->create();
        $rhythmTier = Sponsor::factory()->rhythm()->active()->create();
        $crescendoTier = Sponsor::factory()->crescendo()->active()->create();

        expect($harmonyTier->sponsored_memberships)->toBe(5);
        expect($melodyTier->sponsored_memberships)->toBe(10);
        expect($rhythmTier->sponsored_memberships)->toBe(20);
        expect($crescendoTier->sponsored_memberships)->toBe(25);
    });

    it('tracks available slots correctly after multiple operations', function () {
        $sponsor = Sponsor::factory()->melody()->active()->create();
        $users = User::factory()->count(5)->create();

        // Initial state - all 10 slots available
        expect($sponsor->availableSlots())->toBe(10);
        expect($sponsor->hasAvailableSlots())->toBeTrue();

        // Assign 3 users
        foreach ($users->take(3) as $user) {
            SponsorshipService::assignMembership($sponsor, $user);
        }
        expect($sponsor->availableSlots())->toBe(7);

        // Revoke 1 user
        SponsorshipService::revokeMembership($sponsor, $users[0]);
        expect($sponsor->availableSlots())->toBe(8);

        // Assign 2 more users
        foreach ($users->slice(3, 2) as $user) {
            SponsorshipService::assignMembership($sponsor, $user);
        }
        expect($sponsor->availableSlots())->toBe(6);
        expect($sponsor->usedSlots())->toBe(4);
    });
});

describe('Sponsorship Workflow: Available Slots Query', function () {
    it('returns correct available slots for sponsor via action', function () {
        $sponsor = Sponsor::factory()->melody()->active()->create();
        $users = User::factory()->count(3)->create();

        // Assign some users
        foreach ($users as $user) {
            SponsorshipService::assignMembership($sponsor, $user);
        }

        $result = SponsorshipService::getAvailableSlots($sponsor);

        expect($result)->toBeArray();
        expect($result)->toHaveKeys(['total', 'used', 'available', 'has_available']);
        expect($result['total'])->toBe(10); // Melody tier has 10 slots
        expect($result['used'])->toBe(3);
        expect($result['available'])->toBe(7);
        expect($result['has_available'])->toBeTrue();
    });

    it('returns zero available when sponsor has no slots left', function () {
        $sponsor = Sponsor::factory()->harmony()->active()->create();
        // Harmony tier has 5 slots
        $users = User::factory()->count(5)->create();

        // Fill all slots
        foreach ($users as $user) {
            SponsorshipService::assignMembership($sponsor, $user);
        }

        $result = SponsorshipService::getAvailableSlots($sponsor);

        expect($result['total'])->toBe(5);
        expect($result['used'])->toBe(5);
        expect($result['available'])->toBe(0);
        expect($result['has_available'])->toBeFalse();
    });

    it('returns full availability for new sponsor', function () {
        $sponsor = Sponsor::factory()->rhythm()->active()->create();
        // Rhythm tier has 20 slots

        $result = SponsorshipService::getAvailableSlots($sponsor);

        expect($result['total'])->toBe(20);
        expect($result['used'])->toBe(0);
        expect($result['available'])->toBe(20);
        expect($result['has_available'])->toBeTrue();
    });
});
