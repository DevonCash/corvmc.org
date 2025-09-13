<?php

use App\Models\MemberProfile;
use App\Models\User;
use App\Facades\MemberProfileService;

beforeEach(function () {
    $this->user = User::factory()->createWithoutProfile();

    // Create profile manually for controlled testing
    $this->profile = MemberProfile::create([
        'user_id' => $this->user->id,
        'visibility' => 'public',
        'bio' => 'Test musician profile',
    ]);
});

describe('Profile Visibility Management', function () {
    it('can update profile visibility', function () {
        $result = MemberProfileService::updateVisibility($this->profile, 'private');

        expect($result)->toBeTrue();

        $this->profile->refresh();
        expect($this->profile->visibility)->toBe('private');
    });

    it('rejects invalid visibility values', function () {
        $result = MemberProfileService::updateVisibility($this->profile, 'invalid');

        expect($result)->toBeFalse();

        $this->profile->refresh();
        expect($this->profile->visibility)->toBe('public'); // Should remain unchanged
    });

    it('accepts all valid visibility values', function () {
        $validValues = ['public', 'members', 'private'];

        foreach ($validValues as $value) {
            $result = MemberProfileService::updateVisibility($this->profile, $value);

            expect($result)->toBeTrue();

            $this->profile->refresh();
            expect($this->profile->visibility)->toBe($value);
        }
    });
});

describe('Profile Tags Management', function () {
    it('can update profile skills', function () {
        $skills = ['guitar', 'vocals', 'songwriting'];

        $result = MemberProfileService::updateSkills($this->profile, $skills);

        expect($result)->toBeTrue();

        $this->profile->refresh();
        $profileSkills = $this->profile->skills;

        expect($profileSkills)->toHaveCount(3)
            ->and($profileSkills)->toContain('guitar')
            ->and($profileSkills)->toContain('vocals')
            ->and($profileSkills)->toContain('songwriting');
    });

    it('replaces existing skills', function () {
        // Set initial skills
        $this->profile->attachTag('drums', 'skill');
        $this->profile->attachTag('bass', 'skill');

        $newSkills = ['guitar', 'vocals'];
        $result = MemberProfileService::updateSkills($this->profile, $newSkills);

        expect($result)->toBeTrue();

        $this->profile->refresh();
        $profileSkills = $this->profile->skills;

        expect($profileSkills)->toHaveCount(2)
            ->and($profileSkills)->toContain('guitar')
            ->and($profileSkills)->toContain('vocals')
            ->and($profileSkills)->not->toContain('drums')
            ->and($profileSkills)->not->toContain('bass');
    });

    it('can update profile genres', function () {
        $genres = ['rock', 'indie', 'folk'];

        $result = MemberProfileService::updateGenres($this->profile, $genres);

        expect($result)->toBeTrue();

        $this->profile->refresh();
        $profileGenres = $this->profile->genres;

        expect($profileGenres)->toHaveCount(3)
            ->and($profileGenres)->toContain('rock')
            ->and($profileGenres)->toContain('indie')
            ->and($profileGenres)->toContain('folk');
    });

    it('can update profile influences', function () {
        $influences = ['The Beatles', 'Bob Dylan', 'Radiohead'];

        $result = MemberProfileService::updateInfluences($this->profile, $influences);

        expect($result)->toBeTrue();

        $this->profile->refresh();
        $profileInfluences = $this->profile->influences;

        expect($profileInfluences)->toHaveCount(3)
            ->and($profileInfluences)->toContain('The Beatles')
            ->and($profileInfluences)->toContain('Bob Dylan')
            ->and($profileInfluences)->toContain('Radiohead');
    });
});

describe('Profile Flags Management', function () {
    it('can set profile flags', function () {
        $flags = ['seeking_band', 'available_for_session'];

        $result = MemberProfileService::setFlags($this->profile, $flags);

        expect($result)->toBeTrue();

        $this->profile->refresh();
        expect($this->profile->hasFlag('seeking_band'))->toBeTrue()
            ->and($this->profile->hasFlag('available_for_session'))->toBeTrue();
    });

    it('replaces existing flags', function () {
        // Set initial flags
        $this->profile->flag('seeking_band');
        $this->profile->flag('available_for_gigs');

        $newFlags = ['available_for_session'];
        $result = MemberProfileService::setFlags($this->profile, $newFlags);

        expect($result)->toBeTrue();

        $this->profile->refresh();
        expect($this->profile->hasFlag('seeking_band'))->toBeFalse()
            ->and($this->profile->hasFlag('available_for_gigs'))->toBeFalse()
            ->and($this->profile->hasFlag('available_for_session'))->toBeTrue();
    });
});

describe('Profile Search and Discovery', function () {
    it('can search profiles by query', function () {
        // Create additional profiles
        $user2 = User::factory()->createWithoutProfile(['name' => 'John Guitarist']);
        $profile2 = MemberProfile::create([
            'user_id' => $user2->id,
            'visibility' => 'public',
            'bio' => 'Rock musician',
        ]);

        $user3 = User::factory()->createWithoutProfile(['name' => 'Jane Drummer']);
        $profile3 = MemberProfile::create([
            'user_id' => $user3->id,
            'visibility' => 'public',
            'bio' => 'Jazz enthusiast',
        ]);

        $results = MemberProfileService::searchProfiles(query: 'John');

        expect($results)->toHaveCount(1)
            ->and($results->first()->id)->toBe($profile2->id);
    });

    it('can search profiles by skills', function () {
        // Set up profiles with different skills
        $this->profile->attachTag('guitar', 'skill');

        $user2 = User::factory()->createWithoutProfile();
        $profile2 = MemberProfile::create([
            'user_id' => $user2->id,
            'visibility' => 'public',
        ]);
        $profile2->attachTag('drums', 'skill');

        $results = MemberProfileService::searchProfiles(skills: ['guitar']);

        expect($results)->toHaveCount(1)
            ->and($results->first()->id)->toBe($this->profile->id);
    });

    it('respects visibility in search', function () {
        // Create a private profile
        $user2 = User::factory()->createWithoutProfile();
        $privateProfile = MemberProfile::create([
            'user_id' => $user2->id,
            'visibility' => 'private',
        ]);

        // Search without viewing user (guest)
        $results = MemberProfileService::searchProfiles();

        $profileIds = $results->pluck('id')->toArray();
        expect($profileIds)->toContain($this->profile->id) // Public profile included
            ->and($profileIds)->not->toContain($privateProfile->id); // Private profile excluded
    });

    it('includes own profile in search results', function () {
        $this->profile->update(['visibility' => 'private']);

        $results = MemberProfileService::searchProfiles(viewingUser: $this->user);

        expect($results)->toHaveCount(1)
            ->and($results->first()->id)->toBe($this->profile->id);
    });
});

describe('Directory Statistics', function () {
    it('can get directory stats', function () {
        // Create additional profiles with flags
        $user2 = User::factory()->createWithoutProfile();
        $profile2 = MemberProfile::create([
            'user_id' => $user2->id,
            'visibility' => 'public',
        ]);
        $profile2->flag('seeking_band');

        $stats = MemberProfileService::getDirectoryStats();

        expect($stats)->toHaveKeys([
            'total_members',
            'public_profiles',
            'seeking_bands',
            'available_for_session',
            'top_skills',
            'top_genres'
        ])->and($stats['total_members'])->toBe(2)
            ->and($stats['public_profiles'])->toBe(2)
            ->and($stats['seeking_bands'])->toBe(1);
    });

    it('can get profiles with specific flag', function () {
        $this->profile->flag('seeking_band');

        $user2 = User::factory()->createWithoutProfile();
        $profile2 = MemberProfile::create([
            'user_id' => $user2->id,
            'visibility' => 'public',
        ]);
        $profile2->flag('available_for_session');

        $seekingBandProfiles = MemberProfileService::getProfilesWithFlag('seeking_band');

        expect($seekingBandProfiles)->toHaveCount(1)
            ->and($seekingBandProfiles->first()->id)->toBe($this->profile->id);
    });
});

describe('Collaboration Suggestions', function () {
    it('can suggest collaborators by genres', function () {
        // Set up main profile with genres
        $this->profile->attachTag('rock', 'genre');
        $this->profile->attachTag('indie', 'genre');

        // Create potential collaborator with matching genre
        $user2 = User::factory()->createWithoutProfile();
        $profile2 = MemberProfile::create([
            'user_id' => $user2->id,
            'visibility' => 'public',
        ]);
        $profile2->attachTag('rock', 'genre');

        // Create non-matching profile
        $user3 = User::factory()->createWithoutProfile();
        $profile3 = MemberProfile::create([
            'user_id' => $user3->id,
            'visibility' => 'public',
        ]);
        $profile3->attachTag('jazz', 'genre');

        $suggestions = MemberProfileService::suggestCollaborators($this->profile);

        expect($suggestions)->toHaveCount(1)
            ->and($suggestions->first()->id)->toBe($profile2->id);
    });

    it('returns empty suggestions for profile without tags', function () {
        $suggestions = MemberProfileService::suggestCollaborators($this->profile);

        expect($suggestions)->toHaveCount(0);
    });

    it('excludes private profiles from suggestions', function () {
        $this->profile->attachTag('rock', 'genre');

        $user2 = User::factory()->createWithoutProfile();
        $privateProfile = MemberProfile::create([
            'user_id' => $user2->id,
            'visibility' => 'private',
        ]);
        $privateProfile->attachTag('rock', 'genre');

        $suggestions = MemberProfileService::suggestCollaborators($this->profile);

        expect($suggestions)->toHaveCount(0);
    });
});
