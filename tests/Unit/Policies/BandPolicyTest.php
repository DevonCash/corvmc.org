<?php

namespace Tests\Unit\Policies;

use App\Data\ContactData;
use App\Models\Band;
use App\Models\User;
use App\Policies\BandPolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BandPolicyTest extends TestCase
{
    private BandPolicy $policy;
    private User $owner;
    private User $member;
    private User $admin;
    private User $regularUser;
    private Band $publicBand;
    private Band $membersBand;
    private Band $privateBand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new BandPolicy();

        // Create test users
        $this->owner = User::factory()->withRole('member')->create();
        $this->member = User::factory()->withRole('member')->create();
        $this->admin = User::factory()->withRole('admin')->create();
        $this->regularUser = User::factory()->create();

        // Create test bands with different visibility levels
        $this->publicBand = Band::factory()->create([
            'owner_id' => $this->owner->id,
            'visibility' => 'public',
            'contact' => new ContactData('public')
        ]);

        $this->membersBand = Band::factory()->create([
            'owner_id' => $this->owner->id,
            'visibility' => 'members',
            'contact' => new ContactData('members')
        ]);

        $this->privateBand = Band::factory()->create([
            'owner_id' => $this->owner->id,
            'visibility' => 'private',
            'contact' => new ContactData('private')
        ]);


        // Add member to bands
        $this->publicBand->members()->attach($this->member->id, ['role' => 'member', 'status' => 'active']);
        $this->privateBand->members()->attach($this->member->id, ['role' => 'member', 'status' => 'active']);
    }

    #[Test]
    public function view_any_allows_all_authenticated_users()
    {
        $this->assertTrue($this->policy->viewAny($this->regularUser));
        $this->assertTrue($this->policy->viewAny($this->member));
        $this->assertTrue($this->policy->viewAny($this->owner));
        $this->assertTrue($this->policy->viewAny($this->admin));
    }

    #[Test]
    public function view_allows_anyone_for_public_bands()
    {
        $this->assertTrue($this->policy->view($this->regularUser, $this->publicBand));
        $this->assertTrue($this->policy->view($this->member, $this->publicBand));
        $this->assertTrue($this->policy->view($this->owner, $this->publicBand));
    }

    #[Test]
    public function view_allows_authenticated_users_for_members_bands()
    {
        $this->assertTrue($this->policy->view($this->regularUser, $this->membersBand));
        $this->assertTrue($this->policy->view($this->member, $this->membersBand));
        $this->assertTrue($this->policy->view($this->owner, $this->membersBand));
    }

    #[Test]
    public function view_restricts_private_bands_to_members_and_owner()
    {
        // Owner can view
        $this->assertTrue($this->policy->view($this->owner, $this->privateBand));

        // Member can view
        $this->assertTrue($this->policy->view($this->member, $this->privateBand));

        // Regular user cannot view
        $this->assertNull($this->policy->view($this->regularUser, $this->privateBand));
    }

    #[Test]
    public function create_allows_all_members()
    {
        $this->assertNull($this->policy->create($this->regularUser));
        $this->assertTrue($this->policy->create($this->member));
        $this->assertTrue($this->policy->create($this->owner));
    }

    #[Test]
    public function update_allows_owner()
    {
        $this->assertTrue($this->policy->update($this->owner, $this->publicBand));
        $this->assertTrue($this->policy->update($this->owner, $this->privateBand));
    }

    #[Test]
    public function update_allows_admin_users()
    {
        $this->assertTrue($this->policy->update($this->admin, $this->publicBand));
        $this->assertTrue($this->policy->update($this->admin, $this->privateBand));
    }

    #[Test]
    public function update_allows_band_admin_members()
    {
        // Create band admin member
        $bandAdmin = User::factory()->create();
        $this->publicBand->members()->attach($bandAdmin->id, ['role' => 'admin', 'status' => 'active']);

        $this->assertTrue($this->policy->update($bandAdmin, $this->publicBand));
    }

    #[Test]
    public function update_denies_regular_members()
    {
        $this->assertNull($this->policy->update($this->member, $this->publicBand));
        $this->assertNull($this->policy->update($this->regularUser, $this->publicBand));
    }

    #[Test]
    public function delete_allows_owner()
    {
        $this->assertTrue($this->policy->delete($this->owner, $this->publicBand));
    }

    #[Test]
    public function delete_denies_non_owners()
    {
        $this->assertNull($this->policy->delete($this->member, $this->publicBand));
        $this->assertNull($this->policy->delete($this->regularUser, $this->publicBand));
    }

    #[Test]
    public function force_delete_allows_admins()
    {
        $this->assertTrue($this->policy->forceDelete($this->admin, $this->publicBand));
    }

    #[Test]
    public function force_delete_denies_non_admins()
    {
        $this->assertNull($this->policy->forceDelete($this->owner, $this->publicBand));
        $this->assertNull($this->policy->forceDelete($this->member, $this->publicBand));
        $this->assertNull($this->policy->forceDelete($this->regularUser, $this->publicBand));
    }


    #[Test]
    public function invite_members_delegates_to_manage_members()
    {
        $this->assertTrue($this->policy->invite($this->owner, $this->publicBand));
        $this->assertFalse($this->policy->invite($this->member, $this->publicBand));
    }


    #[Test]
    public function leave_allows_members_but_not_owner()
    {
        $this->assertTrue($this->policy->leave($this->member, $this->privateBand));
        // Not even super admins can leave if they own the band
        $this->assertFalse($this->policy->leave($this->owner, $this->publicBand));
    }

    #[Test]
    public function leave_denies_non_members()
    {
        $this->assertFalse($this->policy->leave($this->regularUser, $this->publicBand));
    }

    #[Test]
    public function transfer_ownership_allows_owner()
    {
        $this->assertTrue($this->policy->transfer($this->owner, $this->publicBand));
    }

    #[Test]
    public function transfer_ownership_denies_non_owners()
    {
        $this->assertNull($this->policy->transfer($this->member, $this->publicBand));
        $this->assertNull($this->policy->transfer($this->regularUser, $this->publicBand));
    }

    #[Test]
    public function view_contact_follows_band_visibility_rules()
    {
        // Public band: anyone can view contact
        $this->assertTrue($this->policy->contact($this->regularUser, $this->publicBand));
        $this->assertTrue($this->policy->contact(null, $this->publicBand));

        // Members band: only authenticated users
        $this->assertTrue($this->policy->contact($this->regularUser, $this->membersBand));
        $this->assertNull($this->policy->contact(null, $this->membersBand));

        // Private band: only members/owner
        $this->assertNull($this->policy->contact($this->regularUser, $this->privateBand));
        $this->assertTrue($this->policy->contact($this->member, $this->privateBand));
        $this->assertTrue($this->policy->contact($this->owner, $this->privateBand));
        $this->assertNull($this->policy->contact(null, $this->privateBand));
    }


    #[Test]
    public function view_handles_unknown_visibility_gracefully()
    {
        $unknownBand = Band::factory()->create([
            'owner_id' => $this->owner->id,
            'visibility' => 'unknown'
        ]);

        $this->assertNull($this->policy->view($this->regularUser, $unknownBand));
    }
}
