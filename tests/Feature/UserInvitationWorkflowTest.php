<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\Invitation;
use App\Models\User;
use App\Facades\UserInvitationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);


describe('UserInvitationService with Invitation Model', function () {

    it('can invite new user and create invitation record', function () {
        Notification::fake();

        $admin = User::factory()->create();
        Auth::login($admin);

        $invitation = UserInvitationService::inviteUser('newuser@example.com', [
            'message' => 'Welcome to CMC!'
        ]);

        expect($invitation)->toBeInstanceOf(Invitation::class)
            ->and($invitation->email)->toBe('newuser@example.com')
            ->and($invitation->message)->toBe('Welcome to CMC!')
            ->and($invitation->inviter_id)->toBe($admin->id)
            ->and($invitation->token)->not->toBeNull()
            ->and($invitation->expires_at)->not->toBeNull()
            ->and($invitation->last_sent_at)->not->toBeNull()
            ->and($invitation->used_at)->toBeNull();

        // Notification was sent
        // TODO: Fix notification assertions
    });

    it('can accept invitation and create new user', function () {
        Notification::fake();

        $inviter = User::factory()->create();
        $invitation = Invitation::create([
            'email' => 'newuser@example.com',
            'message' => 'Join us!',
            'inviter_id' => $inviter->id,
        ]);

        $userData = [
            'name' => 'New User',
            'password' => 'password123',
        ];

        $user = UserInvitationService::acceptInvitation($invitation->token, $userData);

        expect($user)->toBeInstanceOf(User::class)
            ->and($user->name)->toBe('New User')
            ->and($user->email)->toBe('newuser@example.com')
            ->and($user->email_verified_at)->not->toBeNull();

        expect($invitation->fresh()->isUsed())->toBeTrue();

        // User received welcome notification
        // TODO: Fix notification assertions
    });

    it('can accept invitation for existing user', function () {
        Notification::fake();

        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'email_verified_at' => null,
        ]);

        $inviter = User::factory()->create();
        $invitation = Invitation::create([
            'email' => 'existing@example.com',
            'message' => 'Complete your registration!',
            'inviter_id' => $inviter->id,
        ]);

        $userData = [
            'name' => 'Updated Name',
            'password' => 'newpassword',
        ];

        $user = UserInvitationService::acceptInvitation($invitation->token, $userData);

        expect($user->id)->toBe($existingUser->id)
            ->and($user->fresh()->email_verified_at)->not->toBeNull();

        expect($invitation->fresh()->isUsed())->toBeTrue();

        // User received welcome notification
        // TODO: Fix notification assertions
    });

    it('throws exception for expired invitation', function () {
        $inviter = User::factory()->create();
        $invitation = Invitation::create([
            'email' => 'test@example.com',
            'expires_at' => Carbon::now()->subDay(),
            'inviter_id' => $inviter->id,
        ]);

        expect(fn() => UserInvitationService::acceptInvitation($invitation->token, [
            'name' => 'Test User',
            'password' => 'password',
        ]))->toThrow(\Exception::class, 'Invitation has expired.');
    });

    it('throws exception for used invitation', function () {
        $inviter = User::factory()->create();
        $invitation = Invitation::create([
            'email' => 'test@example.com',
            'used_at' => Carbon::now(),
            'inviter_id' => $inviter->id,
        ]);

        expect(fn() => UserInvitationService::acceptInvitation($invitation->token, [
            'name' => 'Test User',
            'password' => 'password',
        ]))->toThrow(\Exception::class, 'Invitation has already been used.');
    });

    it('can resend invitation', function () {
        Notification::fake();

        $admin = User::factory()->create();
        Auth::login($admin);

        // Create original invitation
        $originalInvitation = Invitation::create([
            'email' => 'user@example.com',
            'message' => 'Original message',
            'inviter_id' => $admin->id,
        ]);

        $newInvitation = UserInvitationService::resendInvitation('user@example.com');

        expect($newInvitation->id)->not->toBe($originalInvitation->id)
            ->and($newInvitation->email)->toBe('user@example.com')
            ->and($newInvitation->message)->toBe('Original message')
            ->and($newInvitation->last_sent_at)->not->toBeNull();

        // Notification was sent
        // TODO: Fix notification assertions
    });

    it('throws exception when resending invitation for accepted user', function () {
        $inviter = User::factory()->create();
        $invitation = Invitation::create([
            'email' => 'accepted@example.com',
            'used_at' => Carbon::now(),
            'inviter_id' => $inviter->id,
        ]);

        expect(fn() => UserInvitationService::resendInvitation('accepted@example.com'))
            ->toThrow(\Exception::class, 'User has already accepted invitation.');
    });

    it('can get pending invitations', function () {
        $admin = User::factory()->create();

        // Create pending invitations
        $invitation1 = Invitation::create([
            'email' => 'pending1@example.com',
            'inviter_id' => $admin->id,
        ]);

        $invitation2 = Invitation::create([
            'email' => 'pending2@example.com',
            'inviter_id' => $admin->id,
        ]);

        // Create used invitation (should not appear)
        $usedInvitation = Invitation::create([
            'email' => 'used@example.com',
            'used_at' => Carbon::now(),
            'inviter_id' => $admin->id,
        ]);

        $pending = UserInvitationService::getPendingInvitations();

        expect($pending)->toHaveCount(2);
        expect($pending->contains('id', $invitation1->id))->toBeTrue();
        expect($pending->contains('id', $invitation2->id))->toBeTrue();
        expect($pending->contains('id', $usedInvitation->id))->toBeFalse();
    });

    it('can cancel pending invitation', function () {
        $inviter = User::factory()->create();
        $invitation = Invitation::create([
            'email' => 'cancel@example.com',
            'inviter_id' => $inviter->id,
        ]);

        $result = UserInvitationService::cancelInvitation($invitation);

        expect($result)->toBeTrue();
        expect(Invitation::find($invitation->id))->toBeNull();
    });

    it('cannot cancel used invitation', function () {
        $inviter = User::factory()->create();
        $invitation = Invitation::create([
            'email' => 'used@example.com',
            'used_at' => Carbon::now(),
            'inviter_id' => $inviter->id,
        ]);

        // Debug: Check if invitation is properly marked as used
        expect($invitation->isUsed())->toBeTrue();

        $result = UserInvitationService::cancelInvitation($invitation);

        expect($result)->toBeFalse();
        expect(Invitation::withoutGlobalScopes()->find($invitation->id))->not->toBeNull();
    });

    it('provides invitation statistics', function () {
        // Create various invitations
        $inviter = User::factory()->create();
        Invitation::create(['email' => 'pending1@example.com', 'inviter_id' => $inviter->id]);
        Invitation::create(['email' => 'pending2@example.com', 'inviter_id' => $inviter->id]);
        Invitation::create(['email' => 'used@example.com', 'used_at' => Carbon::now(), 'inviter_id' => $inviter->id]);
        Invitation::create([
            'email' => 'expired@example.com',
            'expires_at' => Carbon::now()->subDay(),
            'inviter_id' => $inviter->id
        ]);

        $stats = UserInvitationService::getInvitationStats();

        expect($stats['total_invitations'])->toBe(4)
            ->and($stats['pending_invitations'])->toBe(3) // 2 active + 1 expired
            ->and($stats['accepted_invitations'])->toBe(1)
            ->and($stats['expired_invitations'])->toBe(1)
            ->and($stats['acceptance_rate'])->toBe(25.0) // 1/4 = 25%
            ->and($stats['pending_active'])->toBe(2); // 3 pending - 1 expired
    });

    it('can find invitation by token', function () {
        $inviter = User::factory()->create();
        $invitation = Invitation::create([
            'email' => 'findme@example.com',
            'inviter_id' => $inviter->id,
        ]);

        $found = UserInvitationService::findInvitationByToken($invitation->token);

        expect($found)->toBeInstanceOf(Invitation::class)
            ->and($found->id)->toBe($invitation->id);
    });
});

describe('Band Invitation Workflows', function () {

    it('can invite user with band for new user', function () {
        Notification::fake();

        $admin = User::factory()->withRole('admin')->create();
        Auth::login($admin);

        $invitation = UserInvitationService::inviteUserWithBand(
            'bandleader@example.com',
            'The Rock Band',
            ['genre' => 'rock', 'description' => 'A rock band'],
        );

        expect($invitation)->not->toBeNull()
            ->and($invitation->data['band_id'])->not->toBeNull()
            ->and($invitation->email)->toBe('bandleader@example.com')
            ->and($invitation->message)->toContain('The Rock Band');

        // Band ownership notification was sent
        // TODO: Fix notification assertions
    });

    it('will fail if user already exists', function () {
        ['user' => $admin] = User::factory()->createAdmin();
        Auth::login($admin);
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        expect(fn() => UserInvitationService::inviteUserWithBand(
            'existing@example.com',
            'Existing User Band',
            ['genre' => 'jazz']
        ))->toThrow(\Exception::class, 'User with this email already exists.');
    });

    it('confirms band ownership when invitation accepted', function () {
        Notification::fake();

        $band = Band::create([
            'name' => 'Pending Band',
            'status' => 'pending_owner_verification',
            'visibility' => 'members',
        ]);

        // Create invitation with band data
        $inviter = User::factory()->create();
        $invitation = Invitation::create([
            'email' => 'owner@example.com',
            'inviter_id' => $inviter->id,
            'expires_at' => now()->addWeeks(1),
            'last_sent_at' => now(),
            'data' => [
                'band_id' => $band->id,
                'band_role' => 'admin',
            ],
        ]);

        $userData = [
            'name' => 'Band Owner',
            'password' => 'password123',
        ];

        $user = UserInvitationService::acceptInvitation($invitation->token, $userData);

        $band->refresh();

        expect($band->status)->toBe('active')
            ->and($band->owner_id)->toBe($user->id);

        expect($band->memberships()->for($user)->exists())->toBeTrue();
    });
});
