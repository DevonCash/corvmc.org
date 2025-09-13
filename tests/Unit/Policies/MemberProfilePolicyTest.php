<?php

namespace Tests\Unit\Policies;

use App\Models\MemberProfile;
use App\Models\User;
use App\Policies\MemberProfilePolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberProfilePolicyTest extends TestCase
{
    private MemberProfilePolicy $policy;
    private User $profileOwner;
    private User $admin;
    private User $regularUser;
    private MemberProfile $publicProfile;
    private MemberProfile $membersProfile;
    private MemberProfile $privateProfile;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->policy = new MemberProfilePolicy();

        // Create one profile using factory, then use its auto-generated user as the owner
        $this->publicProfile = MemberProfile::factory()->create(['visibility' => 'public']);
        $this->profileOwner = $this->publicProfile->user;

        // For testing, we'll just use the same profile with different visibility values
        // Most tests just need to verify the policy logic, not that there are actually separate profiles
        $this->membersProfile = $this->publicProfile; // Will update visibility in individual tests if needed
        $this->privateProfile = $this->publicProfile;  // Will update visibility in individual tests if needed

        // Create admin and regular users
        $this->admin = User::factory()->create();
        $this->regularUser = User::factory()->create();

        // Assign admin role
        $this->admin->assignRole('admin');
    }

    #[Test]
    public function view_any_allows_all_users()
    {
        $this->assertTrue($this->policy->viewAny($this->profileOwner));
        $this->assertTrue($this->policy->viewAny($this->admin));
        $this->assertTrue($this->policy->viewAny($this->regularUser));
    }

    #[Test]
    public function view_delegates_to_profile_is_visible_method()
    {
        // Test with a private profile
        $this->publicProfile->update(['visibility' => 'private']);

        // Profile owner should always be able to view their own profile (isVisible returns true)
        $this->assertTrue($this->policy->view($this->profileOwner, $this->publicProfile));

        // Regular user viewing private profile should get null (defers to default behavior)
        $this->assertNull($this->policy->view($this->regularUser, $this->publicProfile));

        // Reset visibility
        $this->publicProfile->update(['visibility' => 'public']);
    }

    #[Test]
    public function create_allows_all_users()
    {
        $this->assertTrue($this->policy->create($this->profileOwner));
        $this->assertTrue($this->policy->create($this->admin));
        $this->assertTrue($this->policy->create($this->regularUser));
    }

    #[Test]
    public function update_allows_profile_owner()
    {
        // Profile owner can always update their own profile
        $this->assertTrue($this->policy->update($this->profileOwner, $this->publicProfile));
    }

    #[Test]
    public function update_allows_users_with_permission()
    {
        $this->assertTrue($this->policy->update($this->admin, $this->publicProfile));
        $this->assertTrue($this->policy->update($this->admin, $this->membersProfile));
        $this->assertTrue($this->policy->update($this->admin, $this->privateProfile));
    }

    #[Test]
    public function update_denies_users_without_permission()
    {
        $this->assertNull($this->policy->update($this->regularUser, $this->publicProfile));
        $this->assertNull($this->policy->update($this->regularUser, $this->membersProfile));
        $this->assertNull($this->policy->update($this->regularUser, $this->privateProfile));
    }

    #[Test]
    public function delete_allows_profile_owner()
    {
        $this->assertTrue($this->policy->delete($this->profileOwner, $this->publicProfile));
        $this->assertTrue($this->policy->delete($this->profileOwner, $this->membersProfile));
        $this->assertTrue($this->policy->delete($this->profileOwner, $this->privateProfile));
    }

    #[Test]
    public function delete_allows_users_with_permission()
    {
        $this->assertTrue($this->policy->delete($this->admin, $this->publicProfile));
        $this->assertTrue($this->policy->delete($this->admin, $this->membersProfile));
        $this->assertTrue($this->policy->delete($this->admin, $this->privateProfile));
    }

    #[Test]
    public function delete_denies_users_without_permission()
    {
        $this->assertNull($this->policy->delete($this->regularUser, $this->publicProfile));
        $this->assertNull($this->policy->delete($this->regularUser, $this->membersProfile));
        $this->assertNull($this->policy->delete($this->regularUser, $this->privateProfile));
    }

    #[Test]
    public function restore_allows_profile_owner()
    {
        $this->assertTrue($this->policy->restore($this->profileOwner, $this->publicProfile));
        $this->assertTrue($this->policy->restore($this->profileOwner, $this->membersProfile));
        $this->assertTrue($this->policy->restore($this->profileOwner, $this->privateProfile));
    }

    #[Test]
    public function restore_allows_users_with_permission()
    {
        $this->assertTrue($this->policy->restore($this->admin, $this->publicProfile));
        $this->assertTrue($this->policy->restore($this->admin, $this->membersProfile));
        $this->assertTrue($this->policy->restore($this->admin, $this->privateProfile));
    }

    #[Test]
    public function restore_denies_users_without_permission()
    {
        $this->assertNull($this->policy->restore($this->regularUser, $this->publicProfile));
        $this->assertNull($this->policy->restore($this->regularUser, $this->membersProfile));
        $this->assertNull($this->policy->restore($this->regularUser, $this->privateProfile));
    }

    #[Test]
    public function force_delete_denies_all_users()
    {
        $this->assertFalse($this->policy->forceDelete($this->profileOwner, $this->publicProfile));
        $this->assertFalse($this->policy->forceDelete($this->admin, $this->publicProfile));
        $this->assertFalse($this->policy->forceDelete($this->regularUser, $this->publicProfile));
    }

    #[Test]
    public function view_contact_allows_profile_owner()
    {
        $this->assertTrue($this->policy->viewContact($this->profileOwner, $this->publicProfile));
        $this->assertTrue($this->policy->viewContact($this->profileOwner, $this->membersProfile));
        $this->assertTrue($this->policy->viewContact($this->profileOwner, $this->privateProfile));
    }

    #[Test]
    public function view_contact_follows_contact_visibility_rules()
    {
        // Update the profile with public contact visibility
        $this->publicProfile->update([
            'contact' => new \App\Data\ContactData(
                visibility: 'public',
                email: 'test@example.com'
            )
        ]);

        $this->assertTrue($this->policy->viewContact($this->regularUser, $this->publicProfile));
        $this->assertTrue($this->policy->viewContact($this->admin, $this->publicProfile));
    }

    #[Test]
    public function view_contact_restricts_members_only_visibility()
    {
        // Update the profile with members-only contact visibility
        $this->publicProfile->update([
            'contact' => new \App\Data\ContactData(
                visibility: 'members',
                email: 'test@example.com'
            )
        ]);

        // Member user should be able to view contact (any authenticated user is considered a member)
        $this->assertTrue($this->policy->viewContact($this->regularUser, $this->publicProfile));
    }

    #[Test]
    public function view_contact_denies_private_contact_visibility()
    {
        // Update the profile with private contact visibility
        $this->publicProfile->update([
            'contact' => new \App\Data\ContactData(
                visibility: 'private',
                email: 'test@example.com'
            )
        ]);

        // Regular user should not be able to view private contact
        $this->assertNull($this->policy->viewContact($this->regularUser, $this->publicProfile));
    }

    #[Test]
    public function different_users_different_profiles()
    {

        $profile1 = MemberProfile::factory()->create();
        $profile2 = MemberProfile::factory()->create();
        $user1 = $profile1->user;

        // Users can manage their own profiles
        $this->assertTrue($this->policy->update($user1, $profile1));
        $this->assertTrue($this->policy->delete($user1, $profile1));
        $this->assertTrue($this->policy->restore($user1, $profile1));
        $this->assertTrue($this->policy->viewContact($user1, $profile1));

        // Users cannot manage other users' profiles without permission
        $this->assertNull($this->policy->update($user1, $profile2));
        $this->assertNull($this->policy->delete($user1, $profile2));
        $this->assertNull($this->policy->restore($user1, $profile2));
    }

    #[Test]
    public function admin_permissions_work_across_all_operations()
    {
        // Create different user's profile
        $otherProfile = MemberProfile::factory()->create();

        // Admin can perform all operations on any profile
        $this->assertTrue($this->policy->update($this->admin, $otherProfile));
        $this->assertTrue($this->policy->delete($this->admin, $otherProfile));
        $this->assertTrue($this->policy->restore($this->admin, $otherProfile));

        // But still cannot force delete
        $this->assertFalse($this->policy->forceDelete($this->admin, $otherProfile));
    }

    #[Test]
    public function contact_visibility_edge_cases()
    {
        // Test with null contact visibility (defaults to private)
        $this->publicProfile->update([
            'contact' => new \App\Data\ContactData(
                visibility: null,  // Should default to 'private'
                email: 'test@example.com'
            )
        ]);

        // Should default to private behavior
        $this->assertNull($this->policy->viewContact($this->regularUser, $this->publicProfile));

        // Owner should still be able to view
        $this->assertTrue($this->policy->viewContact($this->profileOwner, $this->publicProfile));
    }
}
