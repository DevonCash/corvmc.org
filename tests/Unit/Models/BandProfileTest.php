<?php

namespace Tests\Unit\Models;

use App\Models\BandProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BandProfileTest extends TestCase
{
    use RefreshDatabase;

    protected BandProfile $band;

    protected User $owner;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->band = BandProfile::factory()->create([
            'owner_id' => $this->owner->id,
        ]);
    }

    #[Test]
    public function it_belongs_to_an_owner()
    {
        $this->assertInstanceOf(User::class, $this->band->owner);
        $this->assertEquals($this->owner->id, $this->band->owner->id);
    }

    #[Test]
    public function it_can_have_members()
    {
        $this->band->members()->attach($this->member->id, [
            'role' => 'guitarist',
            'position' => 'lead',
            'status' => 'active',
        ]);

        $members = $this->band->members;

        $this->assertCount(1, $members);
        $this->assertEquals($this->member->id, $members->first()->id);
        $this->assertEquals('guitarist', $members->first()->pivot->role);
        $this->assertEquals('lead', $members->first()->pivot->position);
        $this->assertEquals('active', $members->first()->pivot->status);
    }

    #[Test]
    public function it_can_filter_active_members()
    {
        // Add active member
        $this->band->members()->attach($this->member->id, [
            'role' => 'guitarist',
            'status' => 'active',
        ]);

        // Add invited member
        $invitedMember = User::factory()->create();
        $this->band->members()->attach($invitedMember->id, [
            'role' => 'bassist',
            'status' => 'invited',
        ]);

        $activeMembers = $this->band->activeMembers;

        $this->assertCount(1, $activeMembers);
        $this->assertEquals($this->member->id, $activeMembers->first()->id);
    }

    #[Test]
    public function it_can_filter_pending_invitations()
    {
        // Add active member
        $this->band->members()->attach($this->member->id, [
            'role' => 'guitarist',
            'status' => 'active',
        ]);

        // Add invited member
        $invitedMember = User::factory()->create();
        $this->band->members()->attach($invitedMember->id, [
            'role' => 'bassist',
            'status' => 'invited',
        ]);

        $pendingInvitations = $this->band->pendingInvitations;

        $this->assertCount(1, $pendingInvitations);
        $this->assertEquals($invitedMember->id, $pendingInvitations->first()->id);
    }

    #[Test]
    public function it_can_have_genres_as_tags()
    {
        $this->band->attachTag('rock', 'genre');
        $this->band->attachTag('indie', 'genre');

        $genres = $this->band->genres;

        $this->assertCount(2, $genres);
        $this->assertTrue($genres->pluck('name')->contains('rock'));
        $this->assertTrue($genres->pluck('name')->contains('indie'));
    }

    #[Test]
    public function it_can_have_influences_as_tags()
    {
        $this->band->attachTag('The Beatles', 'influence');
        $this->band->attachTag('Radiohead', 'influence');

        $influences = $this->band->influences;

        $this->assertCount(2, $influences);
        $this->assertTrue($influences->pluck('name')->contains('The Beatles'));
        $this->assertTrue($influences->pluck('name')->contains('Radiohead'));
    }

    #[Test]
    public function it_checks_if_user_is_owner()
    {
        $otherUser = User::factory()->create();

        $this->assertTrue($this->band->isOwnedBy($this->owner));
        $this->assertFalse($this->band->isOwnedBy($otherUser));
        $this->assertFalse($this->band->isOwnedBy($this->member));
    }

    #[Test]
    public function it_checks_if_user_is_member()
    {
        $otherUser = User::factory()->create();

        // Add member
        $this->band->members()->attach($this->member->id, ['status' => 'active']);

        $this->assertTrue($this->band->hasMember($this->member));
        $this->assertFalse($this->band->hasMember($otherUser));
        $this->assertFalse($this->band->hasMember($this->owner)); // Owner is not automatically a member
    }

    #[Test]
    public function it_checks_if_user_is_admin()
    {
        $adminMember = User::factory()->create();
        $regularMember = User::factory()->create();

        // Add admin member
        $this->band->members()->attach($adminMember->id, [
            'role' => 'admin',
            'status' => 'active',
        ]);

        // Add regular member
        $this->band->members()->attach($regularMember->id, [
            'role' => 'member',
            'status' => 'active',
        ]);

        $this->assertTrue($this->band->hasAdmin($adminMember));
        $this->assertFalse($this->band->hasAdmin($regularMember));
        $this->assertFalse($this->band->hasAdmin($this->owner));
    }

    #[Test]
    public function it_gets_user_role()
    {
        // Owner role
        $this->assertEquals('owner', $this->band->getUserRole($this->owner));

        // Member role
        $this->band->members()->attach($this->member->id, [
            'role' => 'guitarist',
            'status' => 'active',
        ]);
        $this->assertEquals('guitarist', $this->band->getUserRole($this->member));

        // Non-member
        $otherUser = User::factory()->create();
        $this->assertNull($this->band->getUserRole($otherUser));
    }

    #[Test]
    public function it_gets_user_position()
    {
        // Add member with position
        $this->band->members()->attach($this->member->id, [
            'role' => 'guitarist',
            'position' => 'lead',
            'status' => 'active',
        ]);

        $this->assertEquals('lead', $this->band->getUserPosition($this->member));

        // Non-member
        $otherUser = User::factory()->create();
        $this->assertNull($this->band->getUserPosition($otherUser));
    }

    #[Test]
    public function it_can_remove_member()
    {
        // Add member
        $this->band->members()->attach($this->member->id, ['status' => 'active']);
        $this->assertTrue($this->band->hasMember($this->member));

        // Remove member
        $this->band->removeMember($this->member);
        $this->assertFalse($this->band->hasMember($this->member));
    }

    #[Test]
    public function it_can_update_member_role()
    {
        // Add member
        $this->band->members()->attach($this->member->id, [
            'role' => 'guitarist',
            'status' => 'active',
        ]);

        $this->assertEquals('guitarist', $this->band->getUserRole($this->member));

        // Update role
        $this->band->updateMemberRole($this->member, 'bassist');
        $this->assertEquals('bassist', $this->band->fresh()->getUserRole($this->member));
    }

    #[Test]
    public function it_can_update_member_position()
    {
        // Add member
        $this->band->members()->attach($this->member->id, [
            'role' => 'guitarist',
            'position' => 'rhythm',
            'status' => 'active',
        ]);

        $this->assertEquals('rhythm', $this->band->getUserPosition($this->member));

        // Update position
        $this->band->updateMemberPosition($this->member, 'lead');
        $this->assertEquals('lead', $this->band->fresh()->getUserPosition($this->member));
    }

    #[Test]
    public function it_returns_null_avatar_url_when_no_media()
    {
        $this->assertNull($this->band->avatar_url);
    }

    #[Test]
    public function it_casts_attributes_correctly()
    {
        $links = ['website' => 'https://example.com', 'spotify' => 'https://spotify.com/artist/123'];
        $contact = ['email' => 'band@example.com', 'phone' => '555-1234'];

        $this->band->update([
            'links' => $links,
            'contact' => $contact,
        ]);

        $this->assertEquals($links, $this->band->links);
        $this->assertEquals($contact, $this->band->contact);
    }

    #[Test]
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'name', 'bio', 'links', 'contact', 'hometown', 'owner_id', 'visibility',
        ];

        $this->assertEquals($fillable, $this->band->getFillable());
    }

    #[Test]
    public function it_can_query_with_touring_bands()
    {
        // Create regular band
        $regularBand = BandProfile::factory()->create();

        // Create touring band (without owner - this might be what makes it touring)
        $touringBand = BandProfile::withTouringBands()->create([
            'name' => 'Touring Band',
            'owner_id' => null,
        ]);

        // Regular query should only return owned bands
        $this->assertCount(2, BandProfile::all()); // $this->band and $regularBand

        // With touring bands should return all
        $this->assertCount(3, BandProfile::withTouringBands()->get());
    }
}
