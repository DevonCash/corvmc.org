<?php

use App\Models\StaffProfile;
use App\Models\User;
use CorvMC\Bands\Models\Band;
use CorvMC\Membership\Actions\Bands\AcceptBandInvitation;
use CorvMC\Membership\Actions\Bands\AddBandMember;
use CorvMC\Membership\Actions\Bands\CreateBand;
use CorvMC\Membership\Actions\MemberProfiles\CreateMemberProfile;
use CorvMC\Membership\Actions\MemberProfiles\DeleteMemberProfile;
use CorvMC\Membership\Actions\MemberProfiles\SearchProfiles;
use CorvMC\Membership\Actions\MemberProfiles\SetFlags;
use CorvMC\Membership\Actions\MemberProfiles\UpdateGenres;
use CorvMC\Membership\Actions\MemberProfiles\UpdateMemberProfile;
use CorvMC\Membership\Actions\MemberProfiles\UpdateVisibility;
use CorvMC\Membership\Actions\StaffProfiles\CreateStaffProfile;
use CorvMC\Membership\Actions\StaffProfiles\DeleteStaffProfile;
use CorvMC\Membership\Actions\StaffProfiles\LinkToUser;
use CorvMC\Membership\Actions\StaffProfiles\ReorderStaffProfiles;
use CorvMC\Membership\Actions\Users\CreateUser;
use CorvMC\Membership\Actions\Users\UpdateUser;
use CorvMC\Membership\Models\MemberProfile;
use CorvMC\Moderation\Enums\Visibility;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

describe('Membership Workflow: Create Band', function () {
    it('creates a band and assigns owner role to creator', function () {
        $user = User::factory()->create();
        Auth::setUser($user);

        $band = CreateBand::run([
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
        expect($membership->status)->toBe('active');
    });

    it('creates a band with tags', function () {
        $user = User::factory()->create();
        Auth::setUser($user);

        $band = CreateBand::run([
            'name' => 'Genre Band',
            'tags' => ['Rock', 'Blues', 'Indie'],
        ]);

        expect($band->tags->count())->toBe(3);
        expect($band->tags->pluck('name')->toArray())->toContain('Rock', 'Blues', 'Indie');
    });
});

describe('Membership Workflow: Band Invitations', function () {
    it('invites a user to join a band with pending status', function () {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        Auth::setUser($owner);

        $band = CreateBand::run(['name' => 'Invitation Test Band']);

        AddBandMember::run($band, $invitee, [
            'role' => 'member',
            'position' => 'Lead Guitarist',
        ]);

        // Check that invitation exists
        $membership = $band->memberships()->where('user_id', $invitee->id)->first();
        expect($membership)->not->toBeNull();
        expect($membership->status)->toBe('invited');
        expect($membership->role)->toBe('member');
        expect($membership->position)->toBe('Lead Guitarist');
        expect($membership->invited_at)->not->toBeNull();
    });

    it('accepts a band invitation and updates status to active', function () {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        Auth::setUser($owner);

        $band = CreateBand::run(['name' => 'Acceptance Test Band']);
        AddBandMember::run($band, $invitee, ['role' => 'member']);

        // Verify pending invitation
        expect($band->memberships()->invited()->where('user_id', $invitee->id)->exists())->toBeTrue();

        // Accept invitation
        AcceptBandInvitation::run($band, $invitee);

        // Verify membership is now active
        $membership = $band->memberships()->where('user_id', $invitee->id)->first();
        expect($membership->status)->toBe('active');
    });

    it('resends invitation when user already has pending invitation', function () {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        Auth::setUser($owner);

        $band = CreateBand::run(['name' => 'Resend Test Band']);
        AddBandMember::run($band, $invitee, [
            'role' => 'member',
            'position' => 'Drummer',
        ]);

        $firstInvitation = $band->memberships()->where('user_id', $invitee->id)->first();
        $firstInvitedAt = $firstInvitation->invited_at;

        // Sleep briefly to ensure timestamp difference
        $this->travel(1)->second();

        // Resend invitation with different position
        AddBandMember::run($band, $invitee, [
            'role' => 'member',
            'position' => 'Vocalist',
        ]);

        // Should still be only one membership record
        expect($band->memberships()->where('user_id', $invitee->id)->count())->toBe(1);

        // Should have updated position and timestamp
        $updatedInvitation = $band->memberships()->where('user_id', $invitee->id)->first();
        expect($updatedInvitation->position)->toBe('Vocalist');
        expect($updatedInvitation->invited_at->isAfter($firstInvitedAt))->toBeTrue();
    });

    it('throws exception when adding already active member', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        Auth::setUser($owner);

        $band = CreateBand::run(['name' => 'Duplicate Test Band']);
        AddBandMember::run($band, $member, ['role' => 'member']);
        AcceptBandInvitation::run($band, $member);

        // Try to add again
        expect(fn () => AddBandMember::run($band, $member))
            ->toThrow(\CorvMC\Bands\Exceptions\BandException::class);
    });
});

describe('Membership Workflow: Profile Management', function () {
    it('updates member profile with bio and hometown', function () {
        $user = User::factory()->create();

        // Use withoutEvents to prevent auto-profile creation then manually create
        $profile = User::withoutEvents(function () use ($user) {
            return MemberProfile::create([
                'user_id' => $user->id,
                'bio' => 'Original bio',
                'hometown' => 'Original City',
            ]);
        });

        $updatedProfile = UpdateMemberProfile::run($profile, [
            'bio' => 'Updated bio with more details',
            'hometown' => 'Portland, OR',
        ]);

        expect($updatedProfile->bio)->toBe('Updated bio with more details');
        expect($updatedProfile->hometown)->toBe('Portland, OR');
    });

    it('updates profile genres through UpdateGenres action', function () {
        $user = User::factory()->create();

        $profile = User::withoutEvents(function () use ($user) {
            return MemberProfile::create([
                'user_id' => $user->id,
            ]);
        });

        UpdateGenres::run($profile, ['Rock', 'Jazz', 'Electronic']);

        $genres = $profile->fresh()->tagsWithType('genre');
        expect($genres->count())->toBe(3);
        expect($genres->pluck('name')->toArray())->toContain('Rock', 'Jazz', 'Electronic');
    });

    it('replaces genres when updating', function () {
        $user = User::factory()->create();

        $profile = User::withoutEvents(function () use ($user) {
            return MemberProfile::create([
                'user_id' => $user->id,
            ]);
        });

        // Set initial genres
        UpdateGenres::run($profile, ['Rock', 'Blues']);
        expect($profile->fresh()->tagsWithType('genre')->count())->toBe(2);

        // Update with new genres (should replace, not add)
        UpdateGenres::run($profile, ['Jazz', 'Classical', 'Folk']);

        $genres = $profile->fresh()->tagsWithType('genre');
        expect($genres->count())->toBe(3);
        expect($genres->pluck('name')->toArray())->toContain('Jazz', 'Classical', 'Folk');
        expect($genres->pluck('name')->toArray())->not->toContain('Rock', 'Blues');
    });

    it('updates profile with genres via UpdateMemberProfile', function () {
        $user = User::factory()->create();

        $profile = User::withoutEvents(function () use ($user) {
            return MemberProfile::create([
                'user_id' => $user->id,
            ]);
        });

        $updatedProfile = UpdateMemberProfile::run($profile, [
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

        $publicProfile = User::withoutEvents(function () use ($publicUser) {
            return MemberProfile::create([
                'user_id' => $publicUser->id,
                'visibility' => Visibility::Public,
            ]);
        });

        $membersProfile = User::withoutEvents(function () use ($membersUser) {
            return MemberProfile::create([
                'user_id' => $membersUser->id,
                'visibility' => Visibility::Members,
            ]);
        });

        $privateProfile = User::withoutEvents(function () use ($privateUser) {
            return MemberProfile::create([
                'user_id' => $privateUser->id,
                'visibility' => Visibility::Private,
            ]);
        });

        expect($publicProfile->visibility)->toBe(Visibility::Public);
        expect($membersProfile->visibility)->toBe(Visibility::Members);
        expect($privateProfile->visibility)->toBe(Visibility::Private);
    });
});

describe('Membership Workflow: User Management', function () {
    it('creates a user with password and email verification', function () {
        $user = CreateUser::run([
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'secure-password-123',
        ]);

        expect($user)->toBeInstanceOf(User::class);
        expect($user->name)->toBe('New User');
        expect($user->email)->toBe('newuser@example.com');
        expect($user->email_verified_at)->not->toBeNull();
    });

    it('throws exception when creating user without password', function () {
        expect(fn () => CreateUser::run([
            'name' => 'No Password User',
            'email' => 'nopassword@example.com',
        ]))->toThrow(\InvalidArgumentException::class, 'Password is required');
    });

    it('updates user information', function () {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $updatedUser = UpdateUser::run($user, [
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

        $profile = CreateMemberProfile::run([
            'user_id' => $user->id,
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

        $profile = User::withoutEvents(function () use ($user) {
            return MemberProfile::create([
                'user_id' => $user->id,
                'bio' => 'Test bio',
            ]);
        });

        UpdateGenres::run($profile, ['Rock', 'Jazz']);
        expect($profile->fresh()->tags->count())->toBe(2);

        $profileId = $profile->id;

        DeleteMemberProfile::run($profile);

        expect(MemberProfile::find($profileId))->toBeNull();
    });

    it('updates profile visibility', function () {
        $user = User::factory()->create();

        $profile = User::withoutEvents(function () use ($user) {
            return MemberProfile::create([
                'user_id' => $user->id,
                'visibility' => Visibility::Public,
            ]);
        });

        expect($profile->visibility)->toBe(Visibility::Public);

        UpdateVisibility::run($profile, Visibility::Private);

        expect($profile->fresh()->visibility)->toBe(Visibility::Private);
    });

    it('sets profile flags', function () {
        $user = User::factory()->create();

        $profile = User::withoutEvents(function () use ($user) {
            return MemberProfile::create([
                'user_id' => $user->id,
            ]);
        });

        SetFlags::run($profile, ['is_teacher', 'is_professional']);

        expect($profile->fresh()->hasFlag('is_teacher'))->toBeTrue();
        expect($profile->fresh()->hasFlag('is_professional'))->toBeTrue();
    });

    it('searches profiles by visibility', function () {
        // Create public profile
        $publicUser = User::factory()->create(['name' => 'Public User']);
        User::withoutEvents(function () use ($publicUser) {
            MemberProfile::create([
                'user_id' => $publicUser->id,
                'visibility' => Visibility::Public,
            ]);
        });

        // Create private profile
        $privateUser = User::factory()->create(['name' => 'Private User']);
        User::withoutEvents(function () use ($privateUser) {
            MemberProfile::create([
                'user_id' => $privateUser->id,
                'visibility' => Visibility::Private,
            ]);
        });

        // Guest search (should only find public)
        $guestResults = SearchProfiles::run(null, null, null, null, null);
        expect($guestResults->pluck('user_id')->toArray())->toContain($publicUser->id);
        expect($guestResults->pluck('user_id')->toArray())->not->toContain($privateUser->id);
    });
});

describe('Membership Workflow: Staff Profiles', function () {
    it('creates a staff profile via action', function () {
        $user = User::factory()->create();

        $staffProfile = CreateStaffProfile::run([
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

        LinkToUser::run($staffProfile, $newUser);

        expect($staffProfile->fresh()->user_id)->toBe($newUser->id);
    });

    it('reorders staff profiles', function () {
        $profile1 = StaffProfile::factory()->create(['sort_order' => 1]);
        $profile2 = StaffProfile::factory()->create(['sort_order' => 2]);
        $profile3 = StaffProfile::factory()->create(['sort_order' => 3]);

        // Reorder: third should be first, first should be third
        ReorderStaffProfiles::run([$profile3->id, $profile2->id, $profile1->id]);

        expect($profile3->fresh()->sort_order)->toBe(1);
        expect($profile2->fresh()->sort_order)->toBe(2);
        expect($profile1->fresh()->sort_order)->toBe(3);
    });

    it('deletes a staff profile', function () {
        $staffProfile = StaffProfile::factory()->create();
        $profileId = $staffProfile->id;

        DeleteStaffProfile::run($staffProfile);

        expect(StaffProfile::find($profileId))->toBeNull();
    });
});
