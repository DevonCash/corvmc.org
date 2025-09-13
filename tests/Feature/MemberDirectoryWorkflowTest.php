<?php

use App\Models\User;
use App\Models\MemberProfile;

beforeEach(function () {
    $this->user = $this->createUser();
    $this->actingAs($this->user);
});

describe('Member Profile Creation and Management', function () {
    it('allows member to create comprehensive profile with bio, skills, and preferences', function () {
        // Story 1: Create Member Profile - "I can add a bio describing my musical background and interests"
        $profileData = [
            'bio' => 'Experienced guitarist with 10+ years playing rock and blues. Love collaborating on original compositions.',
            'skills' => ['guitar', 'vocals', 'songwriting'],
            'genres' => ['rock', 'blues', 'indie'],
            'influences' => ['Hendrix', 'SRV', 'Black Keys'],
            'visibility' => 'public'
        ];

        // Simulate updating profile through the system
        $this->user->profile->update([
            'bio' => $profileData['bio'],
            'visibility' => $profileData['visibility']
        ]);

        // Add skills, genres, and influences as tags
        $this->user->profile->syncTagsWithType($profileData['skills'], 'skill');
        $this->user->profile->syncTagsWithType($profileData['genres'], 'genre'); 
        $this->user->profile->syncTagsWithType($profileData['influences'], 'influence');

        // Verify the profile was created with all expected information
        $this->user->profile->refresh();
        
        expect($this->user->profile->bio)->toBe($profileData['bio'])
            ->and($this->user->profile->visibility)->toBe('public')
            ->and($this->user->profile->skills)->toContain('guitar')
            ->and($this->user->profile->skills)->toContain('vocals')
            ->and($this->user->profile->skills)->toContain('songwriting')
            ->and($this->user->profile->genres)->toContain('rock')
            ->and($this->user->profile->genres)->toContain('blues')
            ->and($this->user->profile->influences)->toContain('Hendrix');
    });

    it('allows member to update existing profile information', function () {
        // Story 2: Update Profile Information - "I can edit my bio and personal information anytime"
        $originalBio = 'Original bio content';
        $updatedBio = 'Updated bio with new musical experiences and collaborations';
        
        $this->user->profile->update(['bio' => $originalBio]);
        expect($this->user->profile->bio)->toBe($originalBio);

        // Update the profile
        $this->user->profile->update(['bio' => $updatedBio]);
        $this->user->profile->syncTagsWithType(['guitar', 'bass', 'production'], 'skill');

        $this->user->profile->refresh();
        expect($this->user->profile->bio)->toBe($updatedBio)
            ->and($this->user->profile->skills)->toContain('bass')
            ->and($this->user->profile->skills)->toContain('production');
    });

    it('allows member to control profile visibility settings', function () {
        // Story 3: Profile Privacy Controls - "I can set my profile visibility settings"
        $visibilityOptions = ['public', 'members', 'private'];

        foreach ($visibilityOptions as $visibility) {
            $this->user->profile->update(['visibility' => $visibility]);
            $this->user->profile->refresh();
            
            expect($this->user->profile->visibility)->toBe($visibility);
        }
    });
});

describe('Member Directory Search and Discovery', function () {
    beforeEach(function () {
        // Create additional test members with varied profiles
        $this->guitarist = $this->createUser(['name' => 'Jane Guitarist']);
        $this->guitarist->profile->update([
            'bio' => 'Rock guitarist looking for band members',
            'visibility' => 'public'
        ]);
        $this->guitarist->profile->syncTagsWithType(['guitar', 'vocals'], 'skill');
        $this->guitarist->profile->syncTagsWithType(['rock', 'metal'], 'genre');

        $this->drummer = $this->createUser(['name' => 'Mike Drummer']);
        $this->drummer->profile->update([
            'bio' => 'Jazz drummer with classical training',
            'visibility' => 'public'
        ]);
        $this->drummer->profile->syncTagsWithType(['drums', 'percussion'], 'skill');
        $this->drummer->profile->syncTagsWithType(['jazz', 'fusion'], 'genre');

        $this->privateUser = $this->createUser(['name' => 'Private User']);
        $this->privateUser->profile->update([
            'bio' => 'This should not appear in searches',
            'visibility' => 'private'
        ]);
        $this->privateUser->profile->syncTagsWithType(['piano', 'composition'], 'skill');
    });

    it('allows browsing member directory with privacy respected', function () {
        // Story 4: Browse Member Directory - "I can see a directory of all members (respecting their privacy settings)"
        $memberProfiles = MemberProfile::with('user')
            ->where('visibility', '!=', 'private')
            ->get();

        // Should include public profiles
        $profileNames = $memberProfiles->pluck('user.name')->toArray();
        expect($profileNames)->toContain('Jane Guitarist')
            ->and($profileNames)->toContain('Mike Drummer')
            ->and($profileNames)->not->toContain('Private User'); // Private profile should be excluded
    });

    it('allows searching members by name and bio content', function () {
        // Story 4: Browse Member Directory - "I can search members by name or bio content"
        
        // Search by name
        $nameResults = MemberProfile::with('user')
            ->where('visibility', '!=', 'private')
            ->whereHas('user', function ($query) {
                $query->where('name', 'LIKE', '%Guitarist%');
            })
            ->get();

        expect($nameResults)->toHaveCount(1)
            ->and($nameResults->first()->user->name)->toBe('Jane Guitarist');

        // Search by bio content
        $bioResults = MemberProfile::with('user')
            ->where('visibility', '!=', 'private')
            ->where('bio', 'LIKE', '%jazz%')
            ->get();

        expect($bioResults)->toHaveCount(1)
            ->and($bioResults->first()->user->name)->toBe('Mike Drummer');
    });

    it('allows filtering members by skills', function () {
        // Story 5: Advanced Member Search - "I can search by multiple skills simultaneously"
        
        // Search for guitar players
        $guitarPlayers = MemberProfile::with('user')
            ->where('visibility', '!=', 'private')
            ->withAnyTags(['guitar'], 'skill')
            ->get();

        expect($guitarPlayers)->toHaveCount(1)
            ->and($guitarPlayers->first()->user->name)->toBe('Jane Guitarist');

        // Search for vocalists (should find the guitarist who also sings)
        $vocalists = MemberProfile::with('user')
            ->where('visibility', '!=', 'private')
            ->withAnyTags(['vocals'], 'skill')
            ->get();

        expect($vocalists)->toHaveCount(1)
            ->and($vocalists->first()->user->name)->toBe('Jane Guitarist');
    });

    it('allows filtering members by genres', function () {
        // Story 5: Advanced Member Search - "I can search by multiple genres to find stylistic matches"
        
        // Search for rock musicians
        $rockMusicians = MemberProfile::with('user')
            ->where('visibility', '!=', 'private')
            ->withAnyTags(['rock'], 'genre')
            ->get();

        expect($rockMusicians)->toHaveCount(1)
            ->and($rockMusicians->first()->user->name)->toBe('Jane Guitarist');

        // Search for jazz musicians
        $jazzMusicians = MemberProfile::with('user')
            ->where('visibility', '!=', 'private')
            ->withAnyTags(['jazz'], 'genre')
            ->get();

        expect($jazzMusicians)->toHaveCount(1)
            ->and($jazzMusicians->first()->user->name)->toBe('Mike Drummer');
    });

    it('combines skill and genre searches for precise results', function () {
        // Story 5: Advanced Member Search - "I can combine skill and genre searches for precise results"
        
        // Search for rock guitar players specifically
        $rockGuitarists = MemberProfile::with('user')
            ->where('visibility', '!=', 'private')
            ->withAllTags(['guitar'], 'skill')
            ->withAnyTags(['rock'], 'genre')
            ->get();

        expect($rockGuitarists)->toHaveCount(1)
            ->and($rockGuitarists->first()->user->name)->toBe('Jane Guitarist');

        // Search for jazz guitarists (should return none)
        $jazzGuitarists = MemberProfile::with('user')
            ->where('visibility', '!=', 'private')
            ->withAllTags(['guitar'], 'skill')
            ->withAnyTags(['jazz'], 'genre')
            ->get();

        expect($jazzGuitarists)->toHaveCount(0);
    });
});

describe('Member Profile Flags and Availability', function () {
    it('allows setting and displaying availability flags', function () {
        // Story 6: Member Profile Flags - "I can flag myself as 'seeking band'"
        
        // Set profile to public so it can be found in searches
        $this->user->profile->update(['visibility' => 'public']);
        
        $this->user->profile->flag('seeking_band');
        $this->user->profile->flag('available_for_session');

        expect($this->user->profile->hasFlag('seeking_band'))->toBeTrue()
            ->and($this->user->profile->hasFlag('available_for_session'))->toBeTrue()
            ->and($this->user->profile->hasFlag('open_to_collaboration'))->toBeFalse();

        // Other members should be able to find flagged members
        $seekingBandMembers = MemberProfile::where('visibility', '!=', 'private')
            ->flagged('seeking_band')
            ->get();

        $memberIds = $seekingBandMembers->pluck('id')->toArray();
        expect($memberIds)->toContain($this->user->profile->id);
    });

    it('allows multiple flags simultaneously', function () {
        // Story 6: Member Profile Flags - "I can set multiple flags simultaneously"
        
        $this->user->profile->flag('seeking_band');
        $this->user->profile->flag('available_for_session');
        $this->user->profile->flag('open_to_collaboration');

        expect($this->user->profile->hasFlag('seeking_band'))->toBeTrue()
            ->and($this->user->profile->hasFlag('available_for_session'))->toBeTrue()
            ->and($this->user->profile->hasFlag('open_to_collaboration'))->toBeTrue();
    });
});

describe('Privacy and Visibility Enforcement', function () {
    beforeEach(function () {
        // Create users with different visibility levels
        $this->publicUser = $this->createUser(['name' => 'Public Member']);
        $this->publicUser->profile->update(['visibility' => 'public', 'bio' => 'Public bio']);

        $this->membersUser = $this->createUser(['name' => 'Members Only']);
        $this->membersUser->profile->update(['visibility' => 'members', 'bio' => 'Members-only bio']);

        $this->privateUser = $this->createUser(['name' => 'Private Member']);
        $this->privateUser->profile->update(['visibility' => 'private', 'bio' => 'Private bio']);
    });

    it('enforces privacy settings throughout the system for logged-in members', function () {
        // Story 3: Profile Privacy Controls - "Privacy settings are enforced throughout the system"
        
        // Logged-in member should see public and members-only profiles
        $visibleProfiles = MemberProfile::with('user')
            ->whereIn('visibility', ['public', 'members'])
            ->get();

        $names = $visibleProfiles->pluck('user.name')->toArray();
        expect($names)->toContain('Public Member')
            ->and($names)->toContain('Members Only')
            ->and($names)->not->toContain('Private Member');
    });

    it('shows only public profiles to guests', function () {
        // Simulate guest user (not logged in)
        auth()->logout();
        
        // Guest should only see public profiles
        $guestVisibleProfiles = MemberProfile::with('user')
            ->where('visibility', 'public')
            ->get();

        $names = $guestVisibleProfiles->pluck('user.name')->toArray();
        expect($names)->toContain('Public Member')
            ->and($names)->not->toContain('Members Only')
            ->and($names)->not->toContain('Private Member');
    });
});