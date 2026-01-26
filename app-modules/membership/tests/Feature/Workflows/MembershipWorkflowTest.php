<?php

use CorvMC\Moderation\Enums\Visibility;
use CorvMC\Bands\Models\Band;
use CorvMC\Membership\Models\MemberProfile;
use App\Models\User;
use CorvMC\Membership\Actions\Bands\AcceptBandInvitation;
use CorvMC\Membership\Actions\Bands\AddBandMember;
use CorvMC\Membership\Actions\Bands\CreateBand;
use CorvMC\Membership\Actions\MemberProfiles\UpdateGenres;
use CorvMC\Membership\Actions\MemberProfiles\UpdateMemberProfile;
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
