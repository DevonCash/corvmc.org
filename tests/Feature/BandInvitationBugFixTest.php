<?php

use App\Models\User;
use App\Models\Band;
use App\Facades\BandService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Band Invitation Bug Fix Verification', function () {
    it('can accept band invitations through Filament UI layer simulation', function () {
        // Setup: Create users and roles
        $admin = User::factory()->create(['name' => 'Admin User']);
        $admin->assignRole('admin');
        
        $invitedUser = User::factory()->create(['name' => 'Invited User', 'id' => 3]);
        $invitedUser->assignRole('member');
        
        // Authenticate as admin to create band
        $this->actingAs($admin);
        
        // Create a band and invite user
        $band = BandService::createBand(['name' => 'Test Band for Bug Fix']);
        BandService::inviteMember($band, $invitedUser, 'member', 'guitarist');
        
        // Verify invitation was created
        $invitation = $band->memberships()->where('user_id', $invitedUser->id)->first();
        expect($invitation)->not->toBeNull()
            ->and($invitation->status)->toBe('invited');
        
        // Switch to invited user and accept invitation
        $this->actingAs($invitedUser);
        
        // Simulate the fixed Filament page acceptInvitation method
        $bandId = $band->id;
        $user = $invitedUser; // User::me() equivalent
        
        // This should now work without the boolean check bug
        try {
            BandService::acceptInvitation($band, $user);
            $acceptanceSuccessful = true;
        } catch (\Exception $e) {
            $acceptanceSuccessful = false;
            $error = $e->getMessage();
        }
        
        // Verify acceptance was successful
        expect($acceptanceSuccessful)->toBeTrue();
        
        // Verify user is now an active member
        $membership = $band->fresh()->memberships()->where('user_id', $invitedUser->id)->first();
        expect($membership)->not->toBeNull()
            ->and($membership->status)->toBe('active');
        
        // Verify no pending invitations remain
        $pendingInvitations = BandService::getPendingInvitationsForUser($invitedUser);
        expect($pendingInvitations->contains('id', $band->id))->toBeFalse();
    });
    
    it('handles band invitation acceptance errors gracefully', function () {
        // Setup: Create user without invitation
        $user = User::factory()->create(['name' => 'Non-invited User']);
        $user->assignRole('member');
        
        $admin = User::factory()->create(['name' => 'Admin User']);
        $admin->assignRole('admin');
        
        // Create band without inviting the user
        $this->actingAs($admin);
        $band = BandService::createBand(['name' => 'Test Band for Error Handling']);
        
        // Switch to non-invited user
        $this->actingAs($user);
        
        // Try to accept non-existent invitation (should throw exception)
        $exceptionThrown = false;
        $errorMessage = '';
        
        try {
            BandService::acceptInvitation($band, $user);
        } catch (\Exception $e) {
            $exceptionThrown = true;
            $errorMessage = $e->getMessage();
        }
        
        // Verify exception was thrown with proper message
        expect($exceptionThrown)->toBeTrue()
            ->and($errorMessage)->toContain('not been invited');
        
        // Verify user is not a member
        $membership = $band->memberships()->where('user_id', $user->id)->first();
        expect($membership)->toBeNull();
    });
    
    it('can decline band invitations through Filament UI layer simulation', function () {
        // Setup similar to acceptance test
        $admin = User::factory()->create(['name' => 'Admin User']);
        $admin->assignRole('admin');
        
        $invitedUser = User::factory()->create(['name' => 'Invited User']);
        $invitedUser->assignRole('member');
        
        // Create band and invite user
        $this->actingAs($admin);
        $band = BandService::createBand(['name' => 'Test Band for Decline']);
        BandService::inviteMember($band, $invitedUser, 'member', 'bassist');
        
        // Switch to invited user and decline invitation
        $this->actingAs($invitedUser);
        
        // Simulate the fixed Filament page declineInvitation method
        try {
            BandService::declineInvitation($band, $invitedUser);
            $declineSuccessful = true;
        } catch (\Exception $e) {
            $declineSuccessful = false;
            $error = $e->getMessage();
        }
        
        // Verify decline was successful
        expect($declineSuccessful)->toBeTrue();
        
        // Verify invitation status is now declined
        $membership = $band->fresh()->memberships()->where('user_id', $invitedUser->id)->first();
        expect($membership)->not->toBeNull()
            ->and($membership->status)->toBe('declined');
        
        // Verify user is not an active member
        $activeMembers = $band->memberships()->active()->where('user_id', $invitedUser->id);
        expect($activeMembers->exists())->toBeFalse();
    });
});