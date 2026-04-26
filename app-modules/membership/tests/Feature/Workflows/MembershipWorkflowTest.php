<?php

use App\Models\StaffProfile;
use App\Models\User;
use CorvMC\Bands\Models\Band;
// Deprecated action classes removed; using service facades instead.
use CorvMC\Membership\Facades\MemberProfileService;
use CorvMC\Membership\Facades\BandService;
use CorvMC\Membership\Facades\StaffProfileService;
use CorvMC\Membership\Facades\UserManagementService;
use CorvMC\Support\Models\Invitation;

use CorvMC\Membership\Models\MemberProfile;
use CorvMC\Moderation\Enums\Visibility;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\Concerns\InteractsWithTime;

beforeEach(function () {
    Notification::fake();
});
// Allow Pest to use InteractsWithTime for travel() helper
uses(InteractsWithTime::class);

describe('Membership Workflow: Create Band', function () {
    it('creates a band and assigns owner role to creator', function () {
        $user = User::factory()->create();
        Auth::setUser($user);

        $band = BandService::create($user, [
            'name' => 'The Test Band',
            'bio' => 'A test band bio',
        ]);

        expect($band)->toBeInstanceOf(Band::class);
        expect($band->name)->toBe('The Test Band');
        expect($band->owner_id)->toBe($user->id);

        // Check that creator is an active member with owner role
        $membership = $band->memberships()->where('user_id', $user->id)->first();
        expect($membership)->not->toBeNull();
        expect($membership->role)->toBe('owner');
    });

    it('creates a band with tags', function () {
        $user = User::factory()->create();
        Auth::setUser($user);

        $band = BandService::create($user, [
            'name' => 'Genre Band',
        ]);

        // Tags not handled by BandService::create(), attach them separately
        $band->attachTags(['Rock', 'Blues', 'Indie']);

        expect($band->tags->count())->toBe(3);
        expect($band->tags->pluck('name')->toArray())->toContain('Rock', 'Blues', 'Indie');
    });
});

describe('Membership Workflow: Band Invitations', function () {
    it('invites a user to join a band with pending invitation', function () {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create($owner, ['name' => 'Invitation Test Band']);

        $invitation = BandService::inviteMember($band, $invitee, 'member', 'Lead Guitarist');

        // Check that invitation exists in support_invitations
        expect($invitation)->toBeInstanceOf(Invitation::class);
        expect($invitation->isPending())->toBeTrue();
        expect($invitation->data['role'])->toBe('member');
        expect($invitation->data['position'])->toBe('Lead Guitarist');

        // User should NOT be a band member yet
        expect($band->isMember($invitee))->toBeFalse();
    });

    it('accepts a band invitation and creates membership', function () {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create($owner, ['name' => 'Acceptance Test Band']);
        $invitation = BandService::inviteMember($band, $invitee, 'member');

        // Verify pending invitation
        expect($invitation->isPending())->toBeTrue();

        // Accept invitation
        BandService::acceptInvitation($invitation);

        // Refresh to clear once() cache on membership()
        $band = $band->fresh();

        // Verify user is now a band member
        expect($band->isMember($invitee))->toBeTrue();
        $membership = $band->membership($invitee);
        expect($membership->role)->toBe('member');

        // Invitation should be accepted
        expect($invitation->fresh()->isAccepted())->toBeTrue();
    });

    it('declines a band invitation', function () {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create($owner, ['name' => 'Decline Test Band']);
        $invitation = BandService::inviteMember($band, $invitee, 'member');

        // Decline invitation
        BandService::declineInvitation($invitation);

        // User should NOT be a band member
        expect($band->isMember($invitee))->toBeFalse();
        expect($invitation->fresh()->isDeclined())->toBeTrue();
    });

    it('retracts a pending invitation', function () {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create($owner, ['name' => 'Retract Test Band']);
        $invitation = BandService::inviteMember($band, $invitee, 'member');

        // Retract invitation
        BandService::retractInvitation($invitation);

        // Invitation should be deleted
        expect(Invitation::find($invitation->id))->toBeNull();
        expect($band->isMember($invitee))->toBeFalse();
    });

    it('prevents duplicate invitations for same user and band', function () {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        Auth::setUser($owner);

        $band = BandService::create($owner, ['name' => 'Duplicate Test Band']);
        BandService::inviteMember($band, $invitee, 'member');

        // Try to invite again — should throw due to unique constraint
        expect(fn() => BandService::inviteMember($band, $invitee, 'member'))
            ->toThrow(\Exception::class);
    });
});

describe('Membership Workflow: Profile Management', function () {
    it('updates member profile with bio and hometown', function () {
        $user = User::factory()->create();
        MemberProfile::where('user_id', $user->id)->delete();
        $profile = MemberProfile::forceCreate(['user_id' => $user->id]);

        $profile->update([
            'bio' => 'Original bio',
            'hometown' => 'Original City',
        ]);

        $updatedProfile = MemberProfileService::update($profile, [
            'bio' => 'Updated bio with more details',
            'hometown' => 'Portland, OR',
        ]);

        expect($updatedProfile->bio)->toBe('Updated bio with more details');
        expect($updatedProfile->hometown)->toBe('Portland, OR');
    });

    it('updates profile genres through UpdateGenres action', function () {
        $user = User::factory()->create();
        MemberProfile::where('user_id', $user->id)->delete();
        $profile = MemberProfile::forceCreate(['user_id' => $user->id]);

        MemberProfileService::updateGenres($profile, ['Rock', 'Jazz', 'Electronic']);

        $genres = $profile->fresh()->tagsWithType('genre');
        expect($genres->count())->toBe(3);
        expect($genres->pluck('name')->toArray())->toContain('Rock', 'Jazz', 'Electronic');
    });

    it('replaces genres when updating', function () {
        $user = User::factory()->create();
        MemberProfile::where('user_id', $user->id)->delete();
        $profile = MemberProfile::forceCreate(['user_id' => $user->id]);

        // Set initial genres
        MemberProfileService::updateGenres($profile, ['Rock', 'Blues']);
        expect($profile->fresh()->tagsWithType('genre')->count())->toBe(2);

        // Update with new genres (should replace, not add)
        MemberProfileService::updateGenres($profile, ['Jazz', 'Classical', 'Folk']);

        $genres = $profile->fresh()->tagsWithType('genre');
        expect($genres->count())->toBe(3);
        expect($genres->pluck('name')->toArray())->toContain('Jazz', 'Classical', 'Folk');
        expect($genres->pluck('name')->toArray())->not->toContain('Rock', 'Blues');
    });

    it('updates profile with genres via UpdateMemberProfile', function () {
        $user = User::factory()->create();
        MemberProfile::where('user_id', $user->id)->delete();
        $profile = MemberProfile::forceCreate(['user_id' => $user->id]);

        $updatedProfile = MemberProfileService::update($profile, [
            'bio' => 'Music lover',
            'genres' => ['Hip Hop', 'R&B', 'Soul'],
        ]);

        $genres = $updatedProfile->tagsWithType('genre');
        expect($genres->count())->toBe(3);
        expect($genres->pluck('name')->toArray())->toContain('Hip Hop', 'R&B', 'Soul');
    });
});

describe('Membership Workflow: Profile Visibility', function () {
    it('creates profiles with different visibility levels', function () {
        $publicUser = User::factory()->create();
        $membersUser = User::factory()->create();
        $privateUser = User::factory()->create();

        MemberProfile::where('user_id', $publicUser->id)->delete();
        $publicProfile = MemberProfile::create(['user_id' => $publicUser->id, 'visibility' => Visibility::Public]);

        MemberProfile::where('user_id', $membersUser->id)->delete();
        $membersProfile = MemberProfile::create(['user_id' => $membersUser->id, 'visibility' => Visibility::Members]);

        MemberProfile::where('user_id', $privateUser->id)->delete();
        $privateProfile = MemberProfile::create(['user_id' => $privateUser->id, 'visibility' => Visibility::Private]);

        expect($publicProfile->fresh()->visibility)->toBe(Visibility::Public);
        expect($membersProfile->fresh()->visibility)->toBe(Visibility::Members);
        expect($privateProfile->fresh()->visibility)->toBe(Visibility::Private);
    });
});

describe('Membership Workflow: User Management', function () {
    it('creates a user with password and email verification', function () {
        $user = UserManagementService::create([
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'secure-password-123',
            'email_verified_at' => now(),
        ]);

        expect($user)->toBeInstanceOf(User::class);
        expect($user->name)->toBe('New User');
        expect($user->email)->toBe('newuser@example.com');
        expect($user->email_verified_at)->not->toBeNull();
    });

    it('updates user information', function () {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $updatedUser = UserManagementService::update($user, [
            'name' => 'Updated Name',
        ]);

        expect($updatedUser->name)->toBe('Updated Name');
        expect($updatedUser->email)->toBe('original@example.com');
    });
});

describe('Membership Workflow: Member Profile Extended', function () {
    it('creates a member profile with skills and genres', function () {
        $user = User::factory()->create();

        // Delete auto-created profile to test manual creation
        MemberProfile::where('user_id', $user->id)->delete();

        $profile = MemberProfileService::create($user, [
            'bio' => 'A musician from Portland',
            'hometown' => 'Portland, OR',
            'skills' => ['Guitar', 'Vocals', 'Songwriting'],
            'genres' => ['Rock', 'Folk'],
        ]);

        expect($profile)->toBeInstanceOf(MemberProfile::class);
        expect($profile->bio)->toBe('A musician from Portland');
        expect($profile->tagsWithType('skill')->count())->toBe(3);
        expect($profile->tagsWithType('genre')->count())->toBe(2);
    });

    it('deletes a member profile with all associated data', function () {
        $user = User::factory()->create();
        MemberProfile::where('user_id', $user->id)->delete();
        $profile = MemberProfile::forceCreate(['user_id' => $user->id]);
        $profile->update(['bio' => 'Test bio']);

        MemberProfileService::updateGenres($profile, ['Rock', 'Jazz']);
        expect($profile->fresh()->tags->count())->toBe(2);

        $profileId = $profile->id;

        MemberProfileService::delete($profile);

        expect(MemberProfile::find($profileId))->toBeNull();
    });

    it('updates profile visibility', function () {
        $user = User::factory()->create();
        MemberProfile::where('user_id', $user->id)->delete();
        $profile = MemberProfile::forceCreate(['user_id' => $user->id]);
        $profile->update(['visibility' => Visibility::Public]);

        expect($profile->fresh()->visibility)->toBe(Visibility::Public);

        MemberProfileService::updateVisibility($profile, Visibility::Private);

        expect($profile->fresh()->visibility)->toBe(Visibility::Private);
    });

    it('sets profile flags', function () {
        $user = User::factory()->create();
        MemberProfile::where('user_id', $user->id)->delete();
        $profile = MemberProfile::forceCreate(['user_id' => $user->id]);

        MemberProfileService::setFlags($profile, ['is_teacher', 'is_professional']);

        expect($profile->fresh()->hasFlag('is_teacher'))->toBeTrue();
        expect($profile->fresh()->hasFlag('is_professional'))->toBeTrue();
    });

    it('searches profiles by visibility', function () {
        // Create public profile
        $publicUser = User::factory()->create(['name' => 'Public User']);
        $publicProfile = MemberProfile::where('user_id', $publicUser->id)->firstOrFail();
        $publicProfile->update(['visibility' => Visibility::Public]);

        // Create private profile
        $privateUser = User::factory()->create(['name' => 'Private User']);
        $privateProfile = MemberProfile::where('user_id', $privateUser->id)->firstOrFail();
        $privateProfile->update(['visibility' => Visibility::Private]);

        // Guest search (should only find public)
        $guestResults = MemberProfileService::searchProfiles(['visibility' => Visibility::Public]);
        expect($guestResults->pluck('user_id')->toArray())->toContain($publicUser->id);
        expect($guestResults->pluck('user_id')->toArray())->not->toContain($privateUser->id);
    });
});

describe('Membership Workflow: Staff Profiles', function () {
    it('creates a staff profile via action', function () {
        $user = User::factory()->create();

        $staffProfile = StaffProfileService::create([
            'name' => 'John Staff',
            'title' => 'Community Manager',
            'bio' => 'Managing the community',
            'user_id' => $user->id,
            'type' => 'staff',
        ]);

        expect($staffProfile)->toBeInstanceOf(StaffProfile::class);
        expect($staffProfile->name)->toBe('John Staff');
        expect($staffProfile->title)->toBe('Community Manager');
    });

    it('links a staff profile to a different user', function () {
        $originalUser = User::factory()->create();
        $newUser = User::factory()->create();

        $staffProfile = StaffProfile::factory()->create([
            'user_id' => $originalUser->id,
        ]);

        expect($staffProfile->user_id)->toBe($originalUser->id);

        StaffProfileService::linkToUser($staffProfile, $newUser);

        expect($staffProfile->fresh()->user_id)->toBe($newUser->id);
    });

    it('reorders staff profiles', function () {
        $profile1 = StaffProfile::factory()->create(['sort_order' => 1]);
        $profile2 = StaffProfile::factory()->create(['sort_order' => 2]);
        $profile3 = StaffProfile::factory()->create(['sort_order' => 3]);

        // Reorder: third should be first, first should be third
        StaffProfileService::reorderStaffProfiles([$profile3->id, $profile2->id, $profile1->id]);

        expect($profile3->fresh()->sort_order)->toBe(1);
        expect($profile2->fresh()->sort_order)->toBe(2);
        expect($profile1->fresh()->sort_order)->toBe(3);
    });

    it('deletes a staff profile', function () {
        $staffProfile = StaffProfile::factory()->create();
        $profileId = $staffProfile->id;

        StaffProfileService::delete($staffProfile);

        expect(StaffProfile::find($profileId))->toBeNull();
    });
});
