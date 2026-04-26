<?php

use App\Models\User;
use CorvMC\Bands\Models\Band;
use CorvMC\Bands\Models\BandMember;
use CorvMC\Membership\Facades\BandService;
use CorvMC\Moderation\Enums\Visibility;
use CorvMC\Support\Models\Invitation;
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

        $band = BandService::create($owner, [
            'name' => 'Public Band',
            'visibility' => Visibility::Public,
        ]);

        expect(\Illuminate\Support\Facades\Gate::forUser(null)->allows('view', $band))->toBeTrue();
    });

    it('makes private bands visible only to members and owner', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create($owner, [
            'name' => 'Private Band',
            'visibility' => Visibility::Private,
        ]);

        // Invite and accept member
        $invitation = BandService::inviteMember($band, $member, 'member');
        BandService::acceptInvitation($invitation);
        $band = $band->fresh();

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

        $band = BandService::create($owner, [
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

        $band = BandService::create($owner, ['name' => 'Role Test Band']);

        // Invite and accept admin
        $adminInvite = BandService::inviteMember($band, $admin, 'admin');
        BandService::acceptInvitation($adminInvite);

        // Invite and accept member
        $memberInvite = BandService::inviteMember($band, $member, 'member');
        BandService::acceptInvitation($memberInvite);

        $band = $band->fresh();

        expect($band->getUserRole($owner))->toBe('owner');
        expect($band->getUserRole($admin))->toBe('admin');
        expect($band->getUserRole($member))->toBe('member');
    });

    it('returns null role for non-member', function () {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create($owner, ['name' => 'Non-Member Test Band']);

        expect($band->getUserRole($outsider))->toBeNull();
    });
});

describe('Band Workflow: Member Management Edge Cases', function () {
    it('throws exception when declining an already declined invitation', function () {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create($owner, ['name' => 'Decline Test Band']);

        $invitation = Invitation::factory()->create([
            'inviter_id' => $owner->id,
            'user_id' => $invitee->id,
            'invitable_type' => 'band',
            'invitable_id' => $band->id,
            'status' => 'declined',
            'responded_at' => now(),
        ]);

        expect(fn () => BandService::declineInvitation($invitation))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('throws exception when retracting a non-pending invitation', function () {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create($owner, ['name' => 'Retract Test Band']);

        $invitation = BandService::inviteMember($band, $invitee, 'member');
        BandService::acceptInvitation($invitation);

        expect(fn () => BandService::retractInvitation($invitation->fresh()))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('allows removing an active member', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create($owner, ['name' => 'Remove Member Test Band']);

        $invitation = BandService::inviteMember($band, $member, 'member');
        BandService::acceptInvitation($invitation);

        $membership = $band->memberships()->where('user_id', $member->id)->first();
        expect($membership)->not->toBeNull();

        $band->removeMember($member);

        expect($band->fresh()->memberships()->where('user_id', $member->id)->exists())->toBeFalse();
    });

    it('deletes band with all members and tags', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create($owner, [
            'name' => 'Delete Test Band',
        ]);

        $invitation = BandService::inviteMember($band, $member, 'member');
        BandService::acceptInvitation($invitation);

        $bandId = $band->id;

        // Verify band exists with members
        expect(Band::find($bandId))->not->toBeNull();
        expect($band->memberships()->count())->toBe(2); // owner + member

        // Delete band
        $result = BandService::delete($band);

        expect($result)->toBeTrue();
        expect(Band::find($bandId))->toBeNull();
        expect(BandMember::where('band_profile_id', $bandId)->count())->toBe(0);
    });

    it('updates band information', function () {
        $owner = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create($owner, [
            'name' => 'Original Name',
            'bio' => 'Original bio',
        ]);

        $updatedBand = BandService::update($band, [
            'name' => 'Updated Name',
            'bio' => 'Updated bio',
            'hometown' => 'Portland, OR',
        ]);

        expect($updatedBand->name)->toBe('Updated Name');
        expect($updatedBand->bio)->toBe('Updated bio');
        expect($updatedBand->hometown)->toBe('Portland, OR');
    });
});

describe('Band Workflow: Ownership', function () {
    it('prevents owner from being invited again', function () {
        $owner = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create($owner, ['name' => 'Owner Duplicate Test Band']);

        // Owner is automatically a member with owner role
        expect($band->memberships()->where('user_id', $owner->id)->exists())->toBeTrue();

        // Trying to invite owner should throw (already a member)
        expect(fn () => BandService::inviteMember($band, $owner, 'member'))
            ->toThrow(\InvalidArgumentException::class);
    });
});
