<?php

use App\Attributes\Story;
use App\Models\Band;
use App\Models\User;
use App\Facades\BandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
});

describe('Story 1: Band Creation Workflow', function () {
    it('completes the full band creation workflow', function () {
        // Given: A CMC member with band creation permissions
        $user = User::factory()->create();
        $user->assignRole('admin'); // Has 'create bands' permission
        $this->actingAs($user);

        // When: They create a comprehensive band profile
        $bandData = [
            'name' => 'The Test Collective',
            'bio' => 'An experimental jazz fusion group exploring the boundaries between electronic and acoustic music.',
            'hometown' => 'Portland, OR',
            'links' => [
                'https://testcollective.bandcamp.com',
                'https://soundcloud.com/test-collective',
                'https://instagram.com/testcollective'
            ],
            'contact' => [
                'email' => 'booking@testcollective.com',
                'phone' => '503-555-0123',
                'visibility' => 'public'
            ],
            'visibility' => 'public'
        ];

        $band = BandService::createBand($bandData);

        // Then: The band is created with all specified details
        expect($band)
            ->toBeInstanceOf(Band::class)
            ->and($band->name)->toBe('The Test Collective')
            ->and($band->bio)->toBe('An experimental jazz fusion group exploring the boundaries between electronic and acoustic music.')
            ->and($band->hometown)->toBe('Portland, OR')
            ->and($band->owner_id)->toBe($user->id)
            ->and($band->visibility)->toBe('public')
            ->and($band->links)->toBe([
                'https://testcollective.bandcamp.com',
                'https://soundcloud.com/test-collective',
                'https://instagram.com/testcollective'
            ]);

        // And: The creator automatically becomes a band member with owner role
        $membership = $band->memberships()->where('user_id', $user->id)->first();
        expect($membership)
            ->not->toBeNull()
            ->and($membership->status)->toBe('active')
            ->and($membership->role)->toBe('owner');

        // And: The band is visible in the community (for public bands)
        $publicBandIds = Band::where('visibility', 'public')->pluck('id')->toArray();
        expect($publicBandIds)->toContain($band->id);
    });

    it('allows adding genre tags and influences during creation', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $bandData = [
            'name' => 'Genre Test Band',
            'bio' => 'Testing genre functionality',
            'tags' => ['jazz', 'fusion', 'experimental'] // Using the tags system
        ];

        $band = BandService::createBand($bandData);

        // Verify tags were attached
        $bandTags = $band->tags->pluck('name')->toArray();
        expect($bandTags)->toContain('jazz', 'fusion', 'experimental');
    });

    it('handles band creation with minimal required information', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        // Minimal band data - just name is required
        $bandData = [
            'name' => 'Minimal Band'
        ];

        $band = BandService::createBand($bandData);

        expect($band)
            ->toBeInstanceOf(Band::class)
            ->and($band->name)->toBe('Minimal Band')
            ->and($band->owner_id)->toBe($user->id);

        // Still gets owner membership
        $membership = $band->memberships()->where('user_id', $user->id)->first();
        expect($membership->role)->toBe('owner');
    });
});

describe('Stories 3 & 4: Existing User Invitation Workflow', function () {
    it('completes the full existing user invitation and acceptance workflow', function () {
        // Given: A band with an admin and a potential member
        $bandOwner = User::factory()->create(['name' => 'Band Owner']);
        $bandOwner->assignRole('admin');

        $potentialMember = User::factory()->create(['name' => 'Potential Member']);
        $potentialMember->assignRole('member');

        $this->actingAs($bandOwner);
        $band = BandService::createBand([
            'name' => 'Invitation Test Band',
            'bio' => 'Testing the invitation workflow'
        ]);

        // When: The band admin invites the existing user
        BandService::inviteMember(
            $band,
            $potentialMember,
            'member',
            'lead guitarist',
            'Guitar Wizard' // custom display name
        );

        // Then: The invitation is created and notifications sent
        $invitation = $band->memberships()->where('user_id', $potentialMember->id)->first();
        expect($invitation)
            ->not->toBeNull()
            ->and($invitation->status)->toBe('invited')
            ->and($invitation->role)->toBe('member')
            ->and($invitation->position)->toBe('lead guitarist')
            ->and($invitation->name)->toBe('Guitar Wizard');

        // And: Notification was sent to the invited user
        Notification::assertCount(1);

        // And: The invited user can see their pending invitations
        $pendingInvitations = BandService::getPendingInvitationsForUser($potentialMember);
        expect($pendingInvitations)->toHaveCount(1);
        expect($pendingInvitations->first()->id)->toBe($band->id);

        // When: The invited user accepts the invitation
        $this->actingAs($potentialMember);
        BandService::acceptInvitation($band, $potentialMember);

        // Then: They become an active band member
        $membership = $band->fresh()->memberships()->where('user_id', $potentialMember->id)->first();
        expect($membership->status)->toBe('active');

        // And: Band admins are notified of acceptance (2 total notifications now)
        Notification::assertCount(2);

        // And: No more pending invitations for this user from this band
        $remainingInvitations = BandService::getPendingInvitationsForUser($potentialMember);
        $bandInvitations = $remainingInvitations->where('id', $band->id);
        expect($bandInvitations)->toHaveCount(0);
    });

    it('allows users to decline invitations', function () {
        $bandOwner = User::factory()->create();
        $bandOwner->assignRole('admin');
        $invitedUser = User::factory()->create();

        $this->actingAs($bandOwner);
        $band = BandService::createBand(['name' => 'Decline Test Band']);

        // Invite the user
        BandService::inviteMember($band, $invitedUser, 'member');

        // User declines the invitation
        $this->actingAs($invitedUser);
        BandService::declineInvitation($band, $invitedUser);

        // Invitation status is updated to declined
        $invitation = $band->fresh()->memberships()->where('user_id', $invitedUser->id)->first();
        expect($invitation->status)->toBe('declined');

        // User is not an active member
        $activeMemberships = $band->memberships()->active()->where('user_id', $invitedUser->id);
        expect($activeMemberships->exists())->toBeFalse();
    });

    it('allows band admins to cancel pending invitations', function () {
        $bandOwner = User::factory()->create();
        $bandOwner->assignRole('admin');
        $invitedUser = User::factory()->create();

        $this->actingAs($bandOwner);
        $band = BandService::createBand(['name' => 'Cancel Test Band']);

        // Send invitation
        BandService::inviteMember($band, $invitedUser);

        // Verify invitation exists
        expect($band->memberships()->invited()->where('user_id', $invitedUser->id)->exists())
            ->toBeTrue();

        // Cancel the invitation
        BandService::cancelInvitation($band, $invitedUser);

        // Invitation no longer exists
        expect($band->fresh()->memberships()->where('user_id', $invitedUser->id)->exists())
            ->toBeFalse();
    });

    it('allows band admins to resend invitations', function () {
        $bandOwner = User::factory()->create();
        $bandOwner->assignRole('admin');
        $invitedUser = User::factory()->create();

        $this->actingAs($bandOwner);
        $band = BandService::createBand(['name' => 'Resend Test Band']);

        // Send initial invitation
        BandService::inviteMember($band, $invitedUser);

        // Clear notification count
        Notification::fake();

        // Resend invitation
        BandService::resendInvitation($band, $invitedUser);

        // New notification sent
        Notification::assertCount(1);

        // Invitation still exists and is updated
        $invitation = $band->fresh()->memberships()->where('user_id', $invitedUser->id)->first();
        expect($invitation)
            ->not->toBeNull()
            ->and($invitation->status)->toBe('invited')
            ->and($invitation->invited_at->isToday())->toBeTrue();
    });

    it('allows re-inviting users who previously declined', function () {
        $bandOwner = User::factory()->create();
        $bandOwner->assignRole('admin');
        $declinedUser = User::factory()->create();

        $this->actingAs($bandOwner);
        $band = BandService::createBand(['name' => 'Re-invite Test Band']);

        // Initial invitation and decline cycle
        BandService::inviteMember($band, $declinedUser);
        $this->actingAs($declinedUser);
        BandService::declineInvitation($band, $declinedUser);

        // Band admin re-invites the declined user
        $this->actingAs($bandOwner);
        BandService::reInviteDeclinedUser($band, $declinedUser);

        // Status is back to invited
        $invitation = $band->fresh()->memberships()->where('user_id', $declinedUser->id)->first();
        expect($invitation->status)->toBe('invited');

        // User can now accept
        $this->actingAs($declinedUser);
        BandService::acceptInvitation($band, $declinedUser);

        $membership = $band->fresh()->memberships()->where('user_id', $declinedUser->id)->first();
        expect($membership->status)->toBe('active');
    });
});

describe('Story 7: Member Role Management Workflow', function () {
    it('completes the full member role management lifecycle', function () {
        // Given: A band with owner and regular members
        $bandOwner = User::factory()->create(['name' => 'Band Owner']);
        $bandOwner->assignRole('admin');

        $member1 = User::factory()->create(['name' => 'Member One']);
        $member2 = User::factory()->create(['name' => 'Member Two']);

        $this->actingAs($bandOwner);
        $band = BandService::createBand(['name' => 'Role Management Test Band']);

        // Add members to the band
        BandService::inviteMember($band, $member1, 'member', 'guitarist');
        BandService::inviteMember($band, $member2, 'member', 'bassist');

        // Members accept invitations
        $this->actingAs($member1);
        BandService::acceptInvitation($band, $member1);
        $this->actingAs($member2);
        BandService::acceptInvitation($band, $member2);

        // Back to band owner for management tasks
        $this->actingAs($bandOwner);

        // When: Owner promotes member1 to admin
        BandService::updateMemberRole($band, $member1, 'admin');

        // Then: Member1 has admin role
        $member1Membership = $band->fresh()->memberships()->where('user_id', $member1->id)->first();
        expect($member1Membership->role)->toBe('admin');

        // When: Owner updates member positions
        BandService::updateMemberPosition($band, $member1, 'lead guitarist');
        BandService::updateMemberPosition($band, $member2, 'bass guitar & vocals');

        // Then: Positions are updated
        $member1Membership = $band->fresh()->memberships()->where('user_id', $member1->id)->first();
        $member2Membership = $band->fresh()->memberships()->where('user_id', $member2->id)->first();
        expect($member1Membership->position)->toBe('lead guitarist');
        expect($member2Membership->position)->toBe('bass guitar & vocals');

        // When: Owner updates display names
        BandService::updateMemberDisplayName($band, $member1, 'Guitar Hero');
        BandService::updateMemberDisplayName($band, $member2, 'Bass Master');

        // Then: Display names are updated
        $member1Membership = $band->fresh()->memberships()->where('user_id', $member1->id)->first();
        $member2Membership = $band->fresh()->memberships()->where('user_id', $member2->id)->first();
        expect($member1Membership->name)->toBe('Guitar Hero');
        expect($member2Membership->name)->toBe('Bass Master');

        // When: Owner removes member2 from the band
        BandService::removeMember($band, $member2);

        // Then: Member2 is no longer in the band
        $remainingMember2 = $band->fresh()->memberships()->where('user_id', $member2->id)->first();
        expect($remainingMember2)->toBeNull();

        // Final state: Band has owner and one admin member
        $allMemberships = $band->fresh()->memberships()->active()->get();
        expect($allMemberships)->toHaveCount(2); // Owner + member1
    });

    it('prevents non-admins from managing members', function () {
        $bandOwner = User::factory()->create();
        $regularMember = User::factory()->create();
        $otherMember = User::factory()->create();

        $this->actingAs($bandOwner);
        $band = BandService::createBand(['name' => 'Permission Test Band']);

        // Add members
        BandService::inviteMember($band, $regularMember, 'member');
        BandService::inviteMember($band, $otherMember, 'member');

        // Accept invitations
        $this->actingAs($regularMember);
        BandService::acceptInvitation($band, $regularMember);
        $this->actingAs($otherMember);
        BandService::acceptInvitation($band, $otherMember);

        // Regular member tries to manage other member (should fail)
        $this->actingAs($regularMember);

        // TODO: These should throw exceptions once authorization is implemented in BandService
        expect(fn() => BandService::updateMemberRole($band, $otherMember, 'admin'))
            ->toThrow(Exception::class);
        expect(fn() => BandService::removeMember($band, $otherMember))
            ->toThrow(Exception::class);
        expect(fn() => BandService::updateMemberPosition($band, $otherMember, 'new position'))
            ->toThrow(Exception::class);
    })->skip('Authorization not yet implemented - see TODOs in BandService');

    it('handles ownership transfer workflow', function () {
        $currentOwner = User::factory()->create(['name' => 'Current Owner']);
        $currentOwner->assignRole('admin');
        $newOwner = User::factory()->create(['name' => 'New Owner']);

        $this->actingAs($currentOwner);
        $band = BandService::createBand(['name' => 'Ownership Transfer Band']);

        // Add future owner as admin
        BandService::inviteMember($band, $newOwner, 'admin');
        $this->actingAs($newOwner);
        BandService::acceptInvitation($band, $newOwner);

        // Transfer ownership
        $this->actingAs($currentOwner);
        BandService::transferOwnership($band, $newOwner);

        // Verify ownership transfer
        $band = $band->fresh();
        expect($band->owner_id)->toBe($newOwner->id);

        // Old owner is now admin
        $oldOwnerMembership = $band->memberships()->where('user_id', $currentOwner->id)->first();
        expect($oldOwnerMembership->role)->toBe('admin');
    });

    it('allows members to leave the band', function () {
        $bandOwner = User::factory()->create();
        $bandOwner->assignRole('admin');
        $member = User::factory()->create();

        $this->actingAs($bandOwner);
        $band = BandService::createBand(['name' => 'Leave Band Test']);

        // Add and accept member
        BandService::inviteMember($band, $member, 'member');
        $this->actingAs($member);
        BandService::acceptInvitation($band, $member);

        // Member leaves the band
        BandService::leaveBand($band, $member);

        // Member is no longer in the band
        $membership = $band->fresh()->memberships()->where('user_id', $member->id)->first();
        expect($membership)->toBeNull();
    });

    it('prevents band owner from leaving their own band', function () {
        $bandOwner = User::factory()->create();
        $bandOwner->assignRole('admin');

        $this->actingAs($bandOwner);
        $band = BandService::createBand(['name' => 'Owner Leave Test']);

        // Owner tries to leave (should fail)
        expect(fn() => BandService::leaveBand($band, $bandOwner))
            ->toThrow(Exception::class);
    });

    it('prevents changing or removing the band owner role', function () {
        $bandOwner = User::factory()->create();
        $bandOwner->assignRole('admin');

        $this->actingAs($bandOwner);
        $band = BandService::createBand(['name' => 'Owner Protection Test']);

        // Try to change owner's role (should fail)
        expect(fn() => BandService::updateMemberRole($band, $bandOwner, 'member'))
            ->toThrow(Exception::class);

        // Try to remove owner (should fail)
        expect(fn() => BandService::removeMember($band, $bandOwner))
            ->toThrow(Exception::class);
    });
});

describe('Story 9: Guest Member Management Workflow', function () {
    it('allows adding guest members without CMC accounts', function () {
        // Given: A band owner
        $bandOwner = User::factory()->create();
        $bandOwner->assignRole('admin');

        $this->actingAs($bandOwner);
        $band = BandService::createBand(['name' => 'Guest Member Test Band']);

        // When: Owner adds a guest member (no user_id)
        BandService::addMember($band, null, [
            'display_name' => 'Session Drummer',
            'role' => 'session musician',
            'position' => 'drums'
        ]);

        // Then: Guest member is added to the roster
        $guestMember = $band->fresh()->memberships()->whereNull('user_id')->first();
        expect($guestMember)
            ->not->toBeNull()
            ->and($guestMember->name)->toBe('Session Drummer')
            ->and($guestMember->role)->toBe('session musician')
            ->and($guestMember->position)->toBe('drums')
            ->and($guestMember->status)->toBe('active')
            ->and($guestMember->user_id)->toBeNull();

        // And: Guest member appears in band roster alongside CMC members
        $allMembers = $band->fresh()->memberships()->active()->get();
        expect($allMembers)->toHaveCount(2); // Owner + guest member
    });

    it('can manage multiple guest members with different roles', function () {
        $bandOwner = User::factory()->create();
        $bandOwner->assignRole('admin');

        $this->actingAs($bandOwner);
        $band = BandService::createBand(['name' => 'Multiple Guests Band']);

        // Add various types of guest members
        BandService::addMember($band, null, [
            'display_name' => 'Former Guitarist',
            'role' => 'former member',
            'position' => 'lead guitar'
        ]);

        BandService::addMember($band, null, [
            'display_name' => 'Sound Engineer',
            'role' => 'crew',
            'position' => 'live sound'
        ]);

        BandService::addMember($band, null, [
            'display_name' => 'Guest Vocalist',
            'role' => 'session musician',
            'position' => 'vocals'
        ]);

        // All guest members are present
        $guestMembers = $band->fresh()->memberships()->whereNull('user_id')->get();
        expect($guestMembers)->toHaveCount(3);

        // Verify each guest member
        $names = $guestMembers->pluck('name')->toArray();
        expect($names)->toContain('Former Guitarist', 'Sound Engineer', 'Guest Vocalist');
    });

    it('distinguishes guest members from CMC members in roster', function () {
        $bandOwner = User::factory()->create(['name' => 'Band Owner']);
        $bandOwner->assignRole('admin');
        $cmcMember = User::factory()->create(['name' => 'CMC Member']);

        $this->actingAs($bandOwner);
        $band = BandService::createBand(['name' => 'Mixed Roster Band']);

        // Add CMC member
        BandService::inviteMember($band, $cmcMember, 'member', 'bassist');
        $this->actingAs($cmcMember);
        BandService::acceptInvitation($band, $cmcMember);

        // Add guest member
        $this->actingAs($bandOwner);
        BandService::addMember($band, null, [
            'display_name' => 'Guest Guitarist',
            'role' => 'session musician',
            'position' => 'guitar'
        ]);

        // Verify roster composition
        $allMembers = $band->fresh()->memberships()->active()->get();
        $cmcMembers = $allMembers->whereNotNull('user_id');
        $guestMembers = $allMembers->whereNull('user_id');

        expect($allMembers)->toHaveCount(3); // Owner + CMC member + guest
        expect($cmcMembers)->toHaveCount(2); // Owner + CMC member
        expect($guestMembers)->toHaveCount(1); // Guest member

        // Check that guest member has different attributes
        $guest = $guestMembers->first();
        $cmcMember = $cmcMembers->where('user_id', '!=', $bandOwner->id)->first();

        expect($guest->user_id)->toBeNull();
        expect($cmcMember->user_id)->not->toBeNull();
    });

    it('allows editing and removing guest members', function () {
        $bandOwner = User::factory()->create();
        $bandOwner->assignRole('admin');

        $this->actingAs($bandOwner);
        $band = BandService::createBand(['name' => 'Edit Guest Band']);

        // Add guest member
        BandService::addMember($band, null, [
            'display_name' => 'Original Name',
            'role' => 'member',
            'position' => 'guitar'
        ]);

        $guestMember = $band->fresh()->memberships()->whereNull('user_id')->first();

        // Edit guest member details (using the BandMember model directly since service methods expect User objects)
        $guestMember->update([
            'name' => 'Updated Name',
            'role' => 'session musician',
            'position' => 'lead guitar'
        ]);

        // Verify updates
        $updatedGuest = $band->fresh()->memberships()->whereNull('user_id')->first();
        expect($updatedGuest->name)->toBe('Updated Name');
        expect($updatedGuest->role)->toBe('session musician');
        expect($updatedGuest->position)->toBe('lead guitar');

        // Remove guest member
        $guestMember->delete();

        // Verify removal
        $remainingGuests = $band->fresh()->memberships()->whereNull('user_id')->get();
        expect($remainingGuests)->toHaveCount(0);
    });

    it('handles guest members with minimal information', function () {
        $bandOwner = User::factory()->create();
        $bandOwner->assignRole('admin');

        $this->actingAs($bandOwner);
        $band = BandService::createBand(['name' => 'Minimal Guest Band']);

        // Add guest with minimal info (just name)
        BandService::addMember($band, null, [
            'display_name' => 'Mystery Member'
            // No role or position specified
        ]);

        $guestMember = $band->fresh()->memberships()->whereNull('user_id')->first();
        expect($guestMember)
            ->not->toBeNull()
            ->and($guestMember->name)->toBe('Mystery Member')
            ->and($guestMember->role)->toBe('member') // Default role
            ->and($guestMember->position)->toBeNull()
            ->and($guestMember->status)->toBe('active');
    });
});
