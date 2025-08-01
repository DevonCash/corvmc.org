<?php

namespace Tests\Unit\Services;

use App\Models\MemberProfile;
use App\Models\User;
use App\Services\MemberProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MemberProfileService $service;
    protected User $user;
    protected MemberProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new MemberProfileService();
        $this->user = User::factory()->createWithoutProfile();
        
        // Create profile manually for controlled testing
        $this->profile = MemberProfile::create([
            'user_id' => $this->user->id,
            'visibility' => 'public',
            'bio' => 'Test musician profile'
        ]);
    }

    #[Test]
    public function it_updates_profile_visibility()
    {
        $this->assertTrue($this->service->updateVisibility($this->profile, 'private'));
        
        $this->profile->refresh();
        $this->assertEquals('private', $this->profile->visibility);
    }

    #[Test]
    public function it_rejects_invalid_visibility_values()
    {
        $this->assertFalse($this->service->updateVisibility($this->profile, 'invalid'));
        
        $this->profile->refresh();
        $this->assertEquals('public', $this->profile->visibility); // Should remain unchanged
    }

    #[Test]
    public function it_accepts_valid_visibility_values()
    {
        $validValues = ['public', 'members', 'private'];
        
        foreach ($validValues as $value) {
            $this->assertTrue($this->service->updateVisibility($this->profile, $value));
            $this->profile->refresh();
            $this->assertEquals($value, $this->profile->visibility);
        }
    }

    #[Test]
    public function it_updates_profile_skills()
    {
        $skills = ['guitar', 'vocals', 'songwriting'];
        
        $this->assertTrue($this->service->updateSkills($this->profile, $skills));
        
        $this->profile->refresh();
        $profileSkills = $this->profile->skills;
        
        $this->assertCount(3, $profileSkills);
        $this->assertContains('guitar', $profileSkills);
        $this->assertContains('vocals', $profileSkills);
        $this->assertContains('songwriting', $profileSkills);
    }

    #[Test]
    public function it_replaces_existing_skills()
    {
        // Set initial skills
        $this->profile->attachTag('drums', 'skill');
        $this->profile->attachTag('bass', 'skill');
        
        $newSkills = ['guitar', 'vocals'];
        $this->assertTrue($this->service->updateSkills($this->profile, $newSkills));
        
        $this->profile->refresh();
        $profileSkills = $this->profile->skills;
        
        $this->assertCount(2, $profileSkills);
        $this->assertContains('guitar', $profileSkills);
        $this->assertContains('vocals', $profileSkills);
        $this->assertNotContains('drums', $profileSkills);
        $this->assertNotContains('bass', $profileSkills);
    }

    #[Test]
    public function it_updates_profile_genres()
    {
        $genres = ['rock', 'indie', 'folk'];
        
        $this->assertTrue($this->service->updateGenres($this->profile, $genres));
        
        $this->profile->refresh();
        $profileGenres = $this->profile->genres;
        
        $this->assertCount(3, $profileGenres);
        $this->assertContains('rock', $profileGenres);
        $this->assertContains('indie', $profileGenres);
        $this->assertContains('folk', $profileGenres);
    }

    #[Test]
    public function it_updates_profile_influences()
    {
        $influences = ['The Beatles', 'Bob Dylan', 'Radiohead'];
        
        $this->assertTrue($this->service->updateInfluences($this->profile, $influences));
        
        $this->profile->refresh();
        $profileInfluences = $this->profile->influences;
        
        $this->assertCount(3, $profileInfluences);
        $this->assertContains('The Beatles', $profileInfluences);
        $this->assertContains('Bob Dylan', $profileInfluences);
        $this->assertContains('Radiohead', $profileInfluences);
    }

    #[Test]
    public function it_sets_profile_flags()
    {
        $flags = ['seeking_band', 'available_for_session'];
        
        $this->assertTrue($this->service->setFlags($this->profile, $flags));
        
        $this->profile->refresh();
        $this->assertTrue($this->profile->hasFlag('seeking_band'));
        $this->assertTrue($this->profile->hasFlag('available_for_session'));
    }

    #[Test]
    public function it_replaces_existing_flags()
    {
        // Set initial flags
        $this->profile->flag('seeking_band');
        $this->profile->flag('available_for_gigs');
        
        $newFlags = ['available_for_session'];
        $this->assertTrue($this->service->setFlags($this->profile, $newFlags));
        
        $this->profile->refresh();
        $this->assertFalse($this->profile->hasFlag('seeking_band'));
        $this->assertFalse($this->profile->hasFlag('available_for_gigs'));
        $this->assertTrue($this->profile->hasFlag('available_for_session'));
    }

    #[Test]
    public function it_searches_profiles_by_query()
    {
        // Create additional profiles
        $user2 = User::factory()->createWithoutProfile(['name' => 'John Guitarist']);
        $profile2 = MemberProfile::create([
            'user_id' => $user2->id,
            'visibility' => 'public',
            'bio' => 'Rock musician'
        ]);

        $user3 = User::factory()->createWithoutProfile(['name' => 'Jane Drummer']);
        $profile3 = MemberProfile::create([
            'user_id' => $user3->id,
            'visibility' => 'public',
            'bio' => 'Jazz enthusiast'
        ]);

        $results = $this->service->searchProfiles(query: 'John');
        
        $this->assertCount(1, $results);
        $this->assertEquals($profile2->id, $results->first()->id);
    }

    #[Test]
    public function it_searches_profiles_by_skills()
    {
        // Set up profiles with different skills
        $this->profile->attachTag('guitar', 'skill');
        
        $user2 = User::factory()->createWithoutProfile();
        $profile2 = MemberProfile::create([
            'user_id' => $user2->id,
            'visibility' => 'public'
        ]);
        $profile2->attachTag('drums', 'skill');

        $results = $this->service->searchProfiles(skills: ['guitar']);
        
        $this->assertCount(1, $results);
        $this->assertEquals($this->profile->id, $results->first()->id);
    }

    #[Test]
    public function it_respects_visibility_in_search()
    {
        // Create a private profile
        $user2 = User::factory()->createWithoutProfile();
        $privateProfile = MemberProfile::create([
            'user_id' => $user2->id,
            'visibility' => 'private'
        ]);

        // Search without viewing user (guest)
        $results = $this->service->searchProfiles();
        
        $profileIds = $results->pluck('id')->toArray();
        $this->assertContains($this->profile->id, $profileIds); // Public profile included
        $this->assertNotContains($privateProfile->id, $profileIds); // Private profile excluded
    }

    #[Test]
    public function it_includes_own_profile_in_search_results()
    {
        $this->profile->update(['visibility' => 'private']);
        
        $results = $this->service->searchProfiles(viewingUser: $this->user);
        
        $this->assertCount(1, $results);
        $this->assertEquals($this->profile->id, $results->first()->id);
    }

    #[Test]
    public function it_gets_directory_stats()
    {
        // Create additional profiles with flags
        $user2 = User::factory()->createWithoutProfile();
        $profile2 = MemberProfile::create([
            'user_id' => $user2->id,
            'visibility' => 'public'
        ]);
        $profile2->flag('seeking_band');

        $stats = $this->service->getDirectoryStats();
        
        $this->assertArrayHasKey('total_members', $stats);
        $this->assertArrayHasKey('public_profiles', $stats);
        $this->assertArrayHasKey('seeking_bands', $stats);
        $this->assertArrayHasKey('available_for_session', $stats);
        $this->assertArrayHasKey('top_skills', $stats);
        $this->assertArrayHasKey('top_genres', $stats);
        
        $this->assertEquals(2, $stats['total_members']);
        $this->assertEquals(2, $stats['public_profiles']);
        $this->assertEquals(1, $stats['seeking_bands']);
    }

    #[Test]
    public function it_gets_profiles_with_specific_flag()
    {
        $this->profile->flag('seeking_band');
        
        $user2 = User::factory()->createWithoutProfile();
        $profile2 = MemberProfile::create([
            'user_id' => $user2->id,
            'visibility' => 'public'
        ]);
        $profile2->flag('available_for_session');

        $seekingBandProfiles = $this->service->getProfilesWithFlag('seeking_band');
        
        $this->assertCount(1, $seekingBandProfiles);
        $this->assertEquals($this->profile->id, $seekingBandProfiles->first()->id);
    }

    #[Test]
    public function it_suggests_collaborators_by_genres()
    {
        // Set up main profile with genres
        $this->profile->attachTag('rock', 'genre');
        $this->profile->attachTag('indie', 'genre');
        
        // Create potential collaborator with matching genre
        $user2 = User::factory()->createWithoutProfile();
        $profile2 = MemberProfile::create([
            'user_id' => $user2->id,
            'visibility' => 'public'
        ]);
        $profile2->attachTag('rock', 'genre');
        
        // Create non-matching profile
        $user3 = User::factory()->createWithoutProfile();
        $profile3 = MemberProfile::create([
            'user_id' => $user3->id,
            'visibility' => 'public'
        ]);
        $profile3->attachTag('jazz', 'genre');

        $suggestions = $this->service->suggestCollaborators($this->profile);
        
        $this->assertCount(1, $suggestions);
        $this->assertEquals($profile2->id, $suggestions->first()->id);
    }

    #[Test]
    public function it_returns_empty_suggestions_for_profile_without_tags()
    {
        $suggestions = $this->service->suggestCollaborators($this->profile);
        
        $this->assertCount(0, $suggestions);
    }

    #[Test]
    public function it_excludes_private_profiles_from_suggestions()
    {
        $this->profile->attachTag('rock', 'genre');
        
        $user2 = User::factory()->createWithoutProfile();
        $privateProfile = MemberProfile::create([
            'user_id' => $user2->id,
            'visibility' => 'private'
        ]);
        $privateProfile->attachTag('rock', 'genre');

        $suggestions = $this->service->suggestCollaborators($this->profile);
        
        $this->assertCount(0, $suggestions);
    }
}