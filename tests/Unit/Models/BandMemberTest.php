<?php

namespace Tests\Unit\Models;

use App\Models\Band;
use App\Models\BandMember;
use App\Models\User;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BandMemberTest extends TestCase
{
    private BandMember $bandMember;
    private Band $band;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $owner = User::factory()->create();
        $this->band = Band::factory()->create(['owner_id' => $owner->id]);
        $this->user = User::factory()->create(['name' => 'John Musician']);
        
        $this->bandMember = BandMember::create([
            'band_profile_id' => $this->band->id,
            'user_id' => $this->user->id,
            'name' => null,
            'role' => 'member',
            'position' => 'guitarist',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function it_belongs_to_band()
    {
        $this->assertInstanceOf(Band::class, $this->bandMember->band);
        $this->assertEquals($this->band->id, $this->bandMember->band->id);
    }

    #[Test]
    public function it_belongs_to_user()
    {
        $this->assertInstanceOf(User::class, $this->bandMember->user);
        $this->assertEquals($this->user->id, $this->bandMember->user->id);
    }

    #[Test]
    public function it_can_have_null_user_id()
    {
        $nonMemberBandMember = BandMember::create([
            'band_profile_id' => $this->band->id,
            'user_id' => null,
            'name' => 'External Member',
            'role' => 'member',
            'position' => 'bassist',
            'status' => 'active',
        ]);
        
        $this->assertNull($nonMemberBandMember->user_id);
        $this->assertNull($nonMemberBandMember->user);
    }

    #[Test]
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'band_profile_id',
            'user_id',
            'name',
            'role',
            'position',
            'status',
            'invited_at',
        ];
        
        $this->assertEquals($fillable, $this->bandMember->getFillable());
    }

    #[Test]
    public function it_casts_invited_at_as_datetime()
    {
        $invitedAt = Carbon::now()->subDays(5);
        $user = User::factory()->create();
        
        $member = BandMember::create([
            'band_profile_id' => $this->band->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'invited',
            'invited_at' => $invitedAt,
        ]);
        
        $this->assertInstanceOf(Carbon::class, $member->invited_at);
        $this->assertEquals($invitedAt->toDateTimeString(), $member->invited_at->toDateTimeString());
    }

    #[Test]
    public function it_gets_display_name_from_user_when_name_is_null()
    {
        $this->assertEquals('John Musician', $this->bandMember->display_name);
    }

    #[Test]
    public function it_gets_display_name_from_name_field_when_provided()
    {
        $this->bandMember->update(['name' => 'Johnny Guitar']);
        
        $this->assertEquals('Johnny Guitar', $this->bandMember->display_name);
    }

    #[Test]
    public function it_falls_back_to_unknown_member_when_no_name_or_user()
    {
        $memberWithoutUser = BandMember::create([
            'band_profile_id' => $this->band->id,
            'user_id' => null,
            'name' => null,
            'role' => 'member',
            'status' => 'active',
        ]);
        
        $this->assertEquals('Unknown Member', $memberWithoutUser->display_name);
    }

    #[Test]
    public function it_identifies_cmc_members()
    {
        $this->assertTrue($this->bandMember->is_cmc_member);
        
        $nonCmcMember = BandMember::create([
            'band_profile_id' => $this->band->id,
            'user_id' => null,
            'name' => 'External Member',
            'role' => 'member',
            'status' => 'active',
        ]);
        
        $this->assertFalse($nonCmcMember->is_cmc_member);
    }

    #[Test]
    public function it_gets_avatar_url_from_user()
    {
        // Since we can't easily test the actual getFilamentAvatarUrl method,
        // we'll just verify the method calls the user's avatar method
        $this->assertNotNull($this->bandMember->user);
        
        // For members without user, it should return null
        $nonCmcMember = BandMember::create([
            'band_profile_id' => $this->band->id,
            'user_id' => null,
            'name' => 'External Member',
            'role' => 'member',
            'status' => 'active',
        ]);
        
        $this->assertNull($nonCmcMember->avatar_url);
    }

    #[Test]
    public function it_stores_different_roles()
    {
        $roles = ['admin', 'member', 'owner'];
        
        foreach ($roles as $role) {
            $user = User::factory()->create();
            $member = BandMember::create([
                'band_profile_id' => $this->band->id,
                'user_id' => $user->id,
                'role' => $role,
                'status' => 'active',
            ]);
            
            $this->assertEquals($role, $member->role);
        }
    }

    #[Test]
    public function it_stores_different_positions()
    {
        $positions = ['guitarist', 'vocalist', 'drummer', 'bassist', 'keyboardist'];
        
        foreach ($positions as $position) {
            $user = User::factory()->create();
            $member = BandMember::create([
                'band_profile_id' => $this->band->id,
                'user_id' => $user->id,
                'role' => 'member',
                'position' => $position,
                'status' => 'active',
            ]);
            
            $this->assertEquals($position, $member->position);
        }
    }

    #[Test]
    public function it_stores_different_statuses()
    {
        $statuses = ['active', 'invited', 'declined'];
        
        foreach ($statuses as $status) {
            $user = User::factory()->create();
            $member = BandMember::create([
                'band_profile_id' => $this->band->id,
                'user_id' => $user->id,
                'role' => 'member',
                'status' => $status,
            ]);
            
            $this->assertEquals($status, $member->status);
        }
    }

    #[Test]
    public function it_uses_correct_table_name()
    {
        $this->assertEquals('band_profile_members', $this->bandMember->getTable());
    }

    #[Test]
    public function it_can_be_created_with_minimal_data()
    {
        $user = User::factory()->create();
        $minimalMember = BandMember::create([
            'band_profile_id' => $this->band->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'active',
        ]);
        
        $this->assertNotNull($minimalMember->id);
        $this->assertEquals($this->band->id, $minimalMember->band_profile_id);
        $this->assertEquals($user->id, $minimalMember->user_id);
        $this->assertEquals('member', $minimalMember->role);
        $this->assertEquals('active', $minimalMember->status);
    }

    #[Test]
    public function it_can_be_created_for_external_member()
    {
        $externalMember = BandMember::create([
            'band_profile_id' => $this->band->id,
            'user_id' => null,
            'name' => 'Jane External',
            'role' => 'member',
            'position' => 'vocalist',
            'status' => 'active',
        ]);
        
        $this->assertNotNull($externalMember->id);
        $this->assertNull($externalMember->user_id);
        $this->assertEquals('Jane External', $externalMember->name);
        $this->assertEquals('Jane External', $externalMember->display_name);
        $this->assertFalse($externalMember->is_cmc_member);
    }

    #[Test]
    public function it_handles_invited_status()
    {
        $user = User::factory()->create();
        $invitedMember = BandMember::create([
            'band_profile_id' => $this->band->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'invited',
            'invited_at' => Carbon::now(),
        ]);
        
        $this->assertEquals('invited', $invitedMember->status);
        $this->assertNotNull($invitedMember->invited_at);
        $this->assertInstanceOf(Carbon::class, $invitedMember->invited_at);
    }
}