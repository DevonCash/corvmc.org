<?php

namespace Tests\Unit\Models;

use App\Data\ContactData;
use App\Models\MemberProfile;
use App\Models\User;
use App\Settings\MemberDirectorySettings;
use PHPUnit\Framework\Attributes\Test;
use Spatie\ModelFlags\Models\Flag;
use Spatie\Tags\Tag;
use Tests\TestCase;

class MemberProfileTest extends TestCase
{
    private MemberProfile $memberProfile;
    private User $user;

    private function createUserWithoutProfile($attributes = []): User
    {
        return User::withoutEvents(function () use ($attributes) {
            return User::factory()->create($attributes);
        });
    }

    private function createProfileForUser(User $user, $attributes = []): MemberProfile
    {
        return MemberProfile::create(array_merge(['user_id' => $user->id], $attributes));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createUserWithoutProfile(['name' => 'John Doe']);
        $this->memberProfile = $this->createProfileForUser($this->user, [
            'bio' => 'Test bio',
            'visibility' => 'public',
        ]);
    }

    #[Test]
    public function it_belongs_to_user()
    {
        $this->assertInstanceOf(User::class, $this->memberProfile->user);
        $this->assertEquals($this->user->id, $this->memberProfile->user->id);
    }

    #[Test]
    public function it_gets_name_from_user()
    {
        $this->assertEquals('John Doe', $this->memberProfile->name);
    }

    #[Test]
    public function it_returns_fallback_avatar_when_no_media()
    {
        $expectedUrl = 'https://ui-avatars.com/api/?name=' . urlencode('John Doe') . '&size=200';
        $this->assertEquals($expectedUrl, $this->memberProfile->avatar);
    }

    #[Test]
    public function it_returns_fallback_avatar_urls_for_different_sizes()
    {
        $baseName = urlencode('John Doe');

        $this->assertEquals(
            "https://ui-avatars.com/api/?name={$baseName}&size=300",
            $this->memberProfile->avatar_url
        );

        $this->assertEquals(
            "https://ui-avatars.com/api/?name={$baseName}&size=100",
            $this->memberProfile->avatar_thumb_url
        );

        $this->assertEquals(
            "https://ui-avatars.com/api/?name={$baseName}&size=600",
            $this->memberProfile->avatar_large_url
        );

        $this->assertEquals(
            "https://ui-avatars.com/api/?name={$baseName}&size=1200",
            $this->memberProfile->avatar_optimized_url
        );
    }

    #[Test]
    public function it_casts_attributes_correctly()
    {
        $links = [['name' => 'Website', 'url' => 'https://example.com']];
        $contact = ['email' => 'test@example.com', 'visibility' => 'members'];
        $embeds = ['<iframe>embed code</iframe>'];

        $profile = MemberProfile::factory()->create([
            'links' => $links,
            'contact' => $contact,
            'embeds' => $embeds,
        ]);

        $this->assertIsArray($profile->links);
        $this->assertInstanceOf(ContactData::class, $profile->contact);
        $this->assertIsArray($profile->embeds);

        $this->assertEquals($links, $profile->links);
        $this->assertEquals($embeds, $profile->embeds);
    }

    #[Test]
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'user_id',
            'bio',
            'hometown',
            'links',
            'contact',
            'embeds',
            'visibility',
        ];

        $this->assertEquals($fillable, $this->memberProfile->getFillable());
    }

    #[Test]
    public function it_checks_if_profile_is_complete()
    {
        // Create tags for testing
        $skillTag = Tag::create(['name' => 'Guitar', 'type' => 'skill']);

        // Profile without bio and skills
        $incompleteProfile = MemberProfile::factory()->create(['bio' => '']);
        $incompleteProfile->detachTags($incompleteProfile->tags);  // Remove all tags
        $this->assertFalse($incompleteProfile->fresh()->isComplete());

        // Profile with bio but no skills
        $profileWithBio = MemberProfile::factory()->create(['bio' => 'Test bio']);
        $profileWithBio->detachTags($profileWithBio->tags);  // Remove all tags
        $this->assertFalse($profileWithBio->fresh()->isComplete());

        // Profile with both bio and skills
        $completeProfile = MemberProfile::factory()->create(['bio' => 'Test bio']);
        $completeProfile->attachTag($skillTag);
        $this->assertTrue($completeProfile->isComplete());
    }

    #[Test]
    public function it_checks_visibility_for_guests()
    {
        $publicProfile = MemberProfile::factory()->create(['visibility' => 'public']);
        $memberProfile = MemberProfile::factory()->create(['visibility' => 'members']);
        $privateProfile = MemberProfile::factory()->create(['visibility' => 'private']);

        $this->assertTrue($publicProfile->isVisible());
        $this->assertFalse($memberProfile->isVisible());
        $this->assertFalse($privateProfile->isVisible());
    }

    #[Test]
    public function it_checks_visibility_for_authenticated_users()
    {
        $user = User::factory()->create();

        $publicProfile = MemberProfile::factory()->create(['visibility' => 'public']);
        $memberProfile = MemberProfile::factory()->create(['visibility' => 'members']);
        $privateProfile = MemberProfile::factory()->create(['visibility' => 'private']);

        $this->assertTrue($publicProfile->isVisible($user));
        $this->assertTrue($memberProfile->isVisible($user));
        $this->assertFalse($privateProfile->isVisible($user));
    }

    #[Test]
    public function it_allows_user_to_see_own_profile()
    {
        $privateProfile = MemberProfile::factory()->create([
            'visibility' => 'private'
        ]);

        $this->assertTrue($privateProfile->isVisible($privateProfile->user));
    }

    #[Test]
    public function it_gets_skills_as_array()
    {
        $skillTag1 = Tag::create(['name' => 'Guitar', 'type' => 'skill']);
        $skillTag2 = Tag::create(['name' => 'Vocals', 'type' => 'skill']);
        $genreTag = Tag::create(['name' => 'Rock', 'type' => 'genre']);

        $this->memberProfile->attachTags([$skillTag1, $skillTag2, $genreTag]);

        $skills = $this->memberProfile->skills;
        $this->assertIsArray($skills);
        $this->assertContains('Guitar', $skills);
        $this->assertContains('Vocals', $skills);
        $this->assertNotContains('Rock', $skills);
    }

    #[Test]
    public function it_gets_influences_as_array()
    {
        $influenceTag1 = Tag::create(['name' => 'The Beatles', 'type' => 'influence']);
        $influenceTag2 = Tag::create(['name' => 'Led Zeppelin', 'type' => 'influence']);
        $skillTag = Tag::create(['name' => 'Guitar', 'type' => 'skill']);

        $this->memberProfile->attachTags([$influenceTag1, $influenceTag2, $skillTag]);

        $influences = $this->memberProfile->influences;
        $this->assertIsArray($influences);
        $this->assertContains('The Beatles', $influences);
        $this->assertContains('Led Zeppelin', $influences);
        $this->assertNotContains('Guitar', $influences);
    }

    #[Test]
    public function it_gets_genres_as_array()
    {
        $genreTag1 = Tag::create(['name' => 'Rock', 'type' => 'genre']);
        $genreTag2 = Tag::create(['name' => 'Jazz', 'type' => 'genre']);
        $skillTag = Tag::create(['name' => 'Guitar', 'type' => 'skill']);

        $this->memberProfile->attachTags([$genreTag1, $genreTag2, $skillTag]);

        $genres = $this->memberProfile->genres;
        $this->assertIsArray($genres);
        $this->assertContains('Rock', $genres);
        $this->assertContains('Jazz', $genres);
        $this->assertNotContains('Guitar', $genres);
    }

    #[Test]
    public function it_can_have_flags()
    {
        $this->memberProfile->flag('seeking_band');

        $this->assertTrue($this->memberProfile->hasFlag('seeking_band'));
        $this->assertFalse($this->memberProfile->hasFlag('looking_for_gigs'));
    }

    #[Test]
    public function it_can_query_profiles_with_specific_flag()
    {
        // Create public profiles to ensure they're visible in the query
        $profile1 = MemberProfile::factory()->create();
        $profile2 = MemberProfile::factory()->create();

        // Create flag directly in database to bypass cache tagging issues with array driver
        $profile1->flag('seeking_band');

        // Verify flag is correctly associated
        $this->assertTrue($profile1->fresh()->hasFlag('seeking_band'));
        $this->assertFalse($profile2->fresh()->hasFlag('seeking_band'));

        // Test the withFlag scope
        $profilesWithFlag = MemberProfile::withoutGlobalScopes()->withFlag('seeking_band')->get();
        $this->assertCount(1, $profilesWithFlag);
        $this->assertEquals($profile1->id, $profilesWithFlag->first()->id);
    }

    #[Test]
    public function it_registers_media_collections()
    {
        $collections = $this->memberProfile->getRegisteredMediaCollections();

        $this->assertCount(1, $collections);
        $this->assertEquals('avatar', $collections->first()->name);
        $this->assertTrue($collections->first()->singleFile);
    }

    #[Test]
    public function it_accepts_correct_mime_types_for_avatar()
    {
        $avatarCollection = $this->memberProfile
            ->getRegisteredMediaCollections()
            ->where('name', 'avatar')
            ->first();

        $acceptedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        // Check if the collection has the expected accepted mime types
        $this->assertNotEmpty($avatarCollection->acceptsMimeTypes);

        foreach ($acceptedMimeTypes as $mimeType) {
            $this->assertContains($mimeType, $avatarCollection->acceptsMimeTypes);
        }

        $this->assertNotContains('text/plain', $avatarCollection->acceptsMimeTypes);
    }

    #[Test]
    public function it_creates_profile_with_factory()
    {
        $profile = MemberProfile::factory()->create();

        $this->assertNotNull($profile->id);
        $this->assertNotNull($profile->user_id);
        // Bio can be null from factory, so just check it's defined
        $this->assertTrue(array_key_exists('bio', $profile->toArray()));
        $this->assertNotNull($profile->visibility);
        $this->assertInstanceOf(User::class, $profile->user);
    }

    #[Test]
    public function it_handles_different_visibility_settings()
    {
        $visibilities = ['public', 'members', 'private'];

        foreach ($visibilities as $visibility) {
            $profile = MemberProfile::factory()->create(['visibility' => $visibility]);
            $this->assertEquals($visibility, $profile->visibility);
        }
    }

    #[Test]
    public function it_handles_empty_contact_data()
    {
        $profile = MemberProfile::factory()->create(['contact' => null]);
        $this->assertNull($profile->contact);
    }

    #[Test]
    public function it_handles_empty_links_array()
    {
        $profile = MemberProfile::factory()->create(['links' => []]);
        $this->assertIsArray($profile->links);
        $this->assertEmpty($profile->links);
    }

    #[Test]
    public function it_handles_empty_embeds_array()
    {
        $profile = MemberProfile::factory()->create(['embeds' => []]);
        $this->assertIsArray($profile->embeds);
        $this->assertEmpty($profile->embeds);
    }
}
