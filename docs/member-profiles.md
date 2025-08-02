# Member Profile System

The Member Profile System manages extended user information for members of the Corvallis Music Collective, providing a comprehensive directory of musicians with their skills, contact information, and collaboration preferences.

## Business Logic & Workflow

The member profile system serves as a self-promotion and discovery platform for musicians within the collective, allowing members to showcase their musical abilities, set their visibility preferences, and connect with other musicians. Each user automatically receives a profile upon registration, which they can then customize with biographical information, skills, musical genres, influences, and contact details. The system implements a three-tier visibility model (public, members-only, private) that gives users granular control over who can see their information.

The workflow centers around member discovery and collaboration facilitation. Members can search for other musicians by skills, genres, or availability flags, making it easy to find potential collaborators for projects or bands. The system includes directory statistics and suggests potential collaborators based on shared musical interests, fostering organic connections within the collective community.

The profile system is intentionally decoupled from other platform operations - it exists purely for member discovery and self-promotion. While the underlying User account connects to band memberships, production management roles, and practice space reservations, the Member Profile itself remains focused on musical identity and discoverability. This separation ensures that members can control their public musical presence independently of their operational activities within the collective.

## API Reference

### MemberProfile Model

#### Properties
```php
// Core Information
public string $bio;                    // HTML-formatted biography
public string $hometown;               // Member's location
public array $links;                   // Social media and website links
public ContactData $contact;           // Contact information with visibility settings
public string $visibility;             // 'public', 'members', or 'private'

// Relationships
public User $user;                     // Associated user account
public Collection $tags;               // Skills, genres, and influences (via Spatie Tags)
public Collection $flags;              // Directory flags (via Spatie ModelFlags)
public Collection $media;              // Avatar and media (via Spatie MediaLibrary)
```

#### Key Methods
```php
// Visibility & Access Control
public function isVisible(?User $user = null): bool
// Check if profile is visible to given user or guest

// Tag Management (Skills, Genres, Influences)
public function getSkillsAttribute(): array
// Get array of skill tag names

public function getGenresAttribute(): array  
// Get array of genre tag names

public function getInfluencesAttribute(): array
// Get array of influence tag names

// Media & Avatar
public function getAvatarUrlAttribute(): string
// Get avatar URL with fallback to default image

public function getAvatarThumbUrlAttribute(): string
// Get thumbnail avatar URL

public function registerMediaConversions(Media $media = null): void
// Configure image conversions (100x100 thumbnails)

// Directory Flags
public function getAvailableFlags(): array
// Get available directory flags from settings

public function getActiveFlagsWithLabels(): array
// Get active flags with human-readable labels

public function scopeWithFlag($query, string $flag)
// Query scope to filter profiles by flag
```

### MemberProfileService API

#### Profile Management
```php
public function updateVisibility(MemberProfile $profile, string $visibility): bool
// Update profile visibility ('public', 'members', 'private')

public function updateSkills(MemberProfile $profile, array $skills): bool
// Replace profile skills with new array

public function updateGenres(MemberProfile $profile, array $genres): bool
// Replace profile genres with new array

public function updateInfluences(MemberProfile $profile, array $influences): bool
// Replace profile influences with new array

public function setFlags(MemberProfile $profile, array $flags): bool
// Replace profile directory flags
```

#### Search & Discovery
```php
public function searchProfiles(
    ?string $query = null,           // Search in name or bio
    ?array $skills = null,           // Filter by skills
    ?array $genres = null,           // Filter by genres  
    ?array $flags = null,            // Filter by directory flags
    ?User $viewingUser = null        // Viewing user for visibility
): Collection
// Search profiles with multiple filter options

public function suggestCollaborators(MemberProfile $profile): Collection
// Find profiles with matching genres/skills for collaboration

public function getProfilesWithFlag(string $flag): Collection
// Get all profiles with specific directory flag

public function getDirectoryStats(): array
// Get member directory statistics
```

#### Statistics & Analytics
```php
public function getDirectoryStats(): array
// Returns:
// - total_members: int
// - public_profiles: int  
// - seeking_bands: int
// - available_for_session: int
// - top_skills: array (name => count)
// - top_genres: array (name => count)
```

## Usage Examples

### Creating and Managing Profiles
```php
// Profiles are automatically created when users register
$user = User::factory()->create();
$profile = $user->profile; // Auto-created via User model events

// Update profile information
$profileService = new MemberProfileService();
$profileService->updateSkills($profile, ['guitar', 'vocals', 'songwriting']);
$profileService->updateGenres($profile, ['rock', 'indie', 'folk']);
$profileService->updateVisibility($profile, 'members');
```

### Searching for Musicians
```php
// Find guitarists interested in collaboration
$guitarists = $profileService->searchProfiles(
    skills: ['guitar'],
    flags: ['open_to_collaboration']
);

// Search for musicians by name or bio
$results = $profileService->searchProfiles(query: 'jazz');

// Get collaboration suggestions for a profile
$suggestions = $profileService->suggestCollaborators($myProfile);
```

### Directory Management
```php
// Get members looking for bands
$seekingBands = $profileService->getProfilesWithFlag('seeking_band');

// Get directory statistics for admin dashboard
$stats = $profileService->getDirectoryStats();
echo "Total members: " . $stats['total_members'];
echo "Top skills: " . json_encode($stats['top_skills']);
```

## Integration Points

- **User Authentication**: Profiles automatically created for new users, but remain separate from operational functions
- **Directory & Discovery**: Primary integration is with search and collaboration matching systems
- **Media Library**: Avatar and image management via Spatie MediaLibrary for visual identity
- **Tagging System**: Skills, genres, and influences via Spatie Tags for categorization and discovery
- **Flag System**: Directory preferences via Spatie ModelFlags for availability and collaboration signals
- **Permission System**: Staff can view private profiles with appropriate permissions for moderation
- **Filament Admin**: Profile management through admin interface for content moderation

**Note**: The Member Profile intentionally does NOT integrate directly with:
- Band management operations (handled at the User level)
- Production management roles (handled at the User level) 
- Practice space reservations (handled at the User level)
- Billing and subscription systems (handled at the User level)

This separation ensures that musical identity and discoverability remain user-controlled and independent of operational platform activities.