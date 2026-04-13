<?php

use App\Models\User;
use CorvMC\Bands\Exceptions\BandException;
use CorvMC\Bands\Models\Band;
use CorvMC\Bands\Models\BandMember;
use CorvMC\Membership\Facades\BandService;
use CorvMC\Moderation\Enums\Visibility;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

describe('Band Model: Visibility', function () {
    it('makes public bands visible to guests', function () {
        $owner = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create([
            'name' => 'Public Band',
            'visibility' => Visibility::Public,
        ]);

        // Guest user (null) - use Gate::forUser with null
        expect(\Illuminate\Support\Facades\Gate::forUser(null)->allows('view', $band))->toBeTrue();
    });

    it('makes private bands visible only to members and owner', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create([
            'name' => 'Private Band',
            'visibility' => Visibility::Private,
        ]);

        // Add and accept member
        BandService::addMember($band, $member, ['role' => 'member']);
        BandService::acceptInvitation($band, $member);
        $band->refresh();

        // Owner can see
        expect($owner->can('view', $band))->toBeTrue();

        // Active member can see
        expect($member->can('view', $band))->toBeTrue();

        // Outsider cannot see
        expect($outsider->can('view', $band))->toBeFalse();

        // Guest cannot see
        expect(\Illuminate\Support\Facades\Gate::forUser(null)->allows('view', $band))->toBeFalse();
    });

    it('makes members-only bands visible to logged in users', function () {
        $owner = User::factory()->create();
        $loggedInUser = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create([
            'name' => 'Members Only Band',
            'visibility' => Visibility::Members,
        ]);

        // Logged in user can see (all logged-in users are considered members)
        expect($loggedInUser->can('view', $band))->toBeTrue();

        // Guest cannot see
        expect(\Illuminate\Support\Facades\Gate::forUser(null)->allows('view', $band))->toBeFalse();
    });
});

describe('Band Model: Member Roles', function () {
    it('returns correct user role for owner/admin/member', function () {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create(['name' => 'Role Test Band']);

        // Add admin
        BandService::addMember($band, $admin, ['role' => 'admin']);
        BandService::acceptInvitation($band, $admin);

        // Add member
        BandService::addMember($band, $member, ['role' => 'member']);
        BandService::acceptInvitation($band, $member);

        $band->refresh();

        expect($band->getUserRole($owner))->toBe('owner');
        expect($band->getUserRole($admin))->toBe('admin');
        expect($band->getUserRole($member))->toBe('member');
    });

    it('returns null role for non-member', function () {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create(['name' => 'Non-Member Test Band']);

        expect($band->getUserRole($outsider))->toBeNull();
    });
});

describe('Band Workflow: Member Management Edge Cases', function () {
    it('throws exception when declining non-existent invitation', function () {
        $owner = User::factory()->create();
        $notInvited = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create(['name' => 'Decline Test Band']);

        expect(fn () => BandService::declineInvitation($band, $notInvited))
            ->toThrow(BandException::class, 'User has not been invited to this band.');
    });

    it('throws exception when cancelling non-existent invitation', function () {
        $owner = User::factory()->create();
        $notInvited = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create(['name' => 'Cancel Test Band']);

        expect(fn () => BandService::cancelInvitation($band, $notInvited))
            ->toThrow(BandException::class, 'User has not been invited to this band.');
    });

    it('allows removing an active member', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create(['name' => 'Remove Member Test Band']);

        BandService::addMember($band, $member, ['role' => 'member']);
        BandService::acceptInvitation($band, $member);

        $membership = $band->memberships()->where('user_id', $member->id)->first();
        expect($membership)->not->toBeNull();

        BandService::removeMember($membership);

        // Verify member was removed
        expect($band->fresh()->memberships()->where('user_id', $member->id)->exists())->toBeFalse();
    });

    it('deletes band with all members and tags', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create([
            'name' => 'Delete Test Band',
            'tags' => ['Rock', 'Blues'],
        ]);

        BandService::addMember($band, $member, ['role' => 'member']);
        BandService::acceptInvitation($band, $member);

        $bandId = $band->id;

        // Verify band exists with members and tags
        expect(Band::find($bandId))->not->toBeNull();
        expect($band->memberships()->count())->toBe(2); // owner + member
        expect($band->tags->count())->toBe(2);

        // Delete band
        $result = BandService::deleteBand($band);

        expect($result)->toBeTrue();
        expect(Band::find($bandId))->toBeNull();
        expect(BandMember::where('band_profile_id', $bandId)->count())->toBe(0);
    });

    it('updates band information', function () {
        $owner = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create([
            'name' => 'Original Name',
            'bio' => 'Original bio',
        ]);

        $updatedBand = BandService::updateBand($band, [
            'name' => 'Updated Name',
            'bio' => 'Updated bio',
            'hometown' => 'Portland, OR',
            'tags' => ['Jazz', 'Funk'],
        ]);

        expect($updatedBand->name)->toBe('Updated Name');
        expect($updatedBand->bio)->toBe('Updated bio');
        expect($updatedBand->hometown)->toBe('Portland, OR');
        expect($updatedBand->tags->pluck('name')->toArray())->toContain('Jazz', 'Funk');
    });
});

describe('Band Workflow: Ownership', function () {
    it('prevents owner from being added as a duplicate member', function () {
        $owner = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create(['name' => 'Owner Duplicate Test Band']);

        // Owner is automatically a member with owner role
        expect($band->memberships()->where('user_id', $owner->id)->exists())->toBeTrue();

        // Trying to add owner again should throw exception
        expect(fn () => BandService::addMember($band, $owner, ['role' => 'member']))
            ->toThrow(BandException::class);
    });
});
