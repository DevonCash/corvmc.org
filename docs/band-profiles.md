# Band Profile System

The Band Profile System manages band information and member relationships within the Corvallis Music Collective, enabling musicians to create bands, invite members, and collaborate on musical projects.

## Business Logic & Workflow

The band profile system facilitates group formation and management within the music collective. Bands are owned by a single user who has administrative control, but can have multiple members with different roles and positions. The system supports a flexible membership model where users can be invited to join bands, and existing members can have their roles updated over time. Each band maintains its own profile with biographical information, social media links, musical genres, and influences, similar to individual member profiles.

The workflow begins when a user creates a band profile and becomes its owner. They can then invite other collective members to join the band with specific roles (admin, member) and positions (guitarist, vocalist, etc.). Invitations create pending relationships that invited users can accept or decline. The system tracks member status (active, invited) and provides administrative functions for managing the band roster. Bands can be tagged with genres and influences to help with discovery and booking opportunities.

The system integrates with the production management system, allowing bands to be attached to shows and events as performers. This creates a comprehensive ecosystem where individual musicians can form bands, get booked for shows, and manage their group activities all within the collective's platform. The band profile also supports media management for band photos and promotional materials, making it a complete band management solution.

## API Reference

### BandProfile Model

#### Properties
```php
// Core Information
public string $name;                   // Band name
public string $bio;                    // HTML-formatted band biography  
public array $links;                   // Social media and website links
public array $contact;                 // Contact information
public string $hometown;               // Band's location/base
public string $visibility;             // Profile visibility setting
public int $owner_id;                  // User ID of band owner

// Relationships
public User $owner;                    // Band owner/creator
public Collection $members;            // All band members (via pivot table)
public Collection $activeMembers;      // Only active members
public Collection $pendingInvitations; // Pending member invitations
public Collection $tags;               // Genres and influences (via Spatie Tags)
public Collection $media;              // Band photos and media (via Spatie MediaLibrary)
```

#### Key Methods
```php
// Member Management
public function isOwnedBy(User $user): bool
// Check if user owns this band

public function hasMember(User $user): bool  
// Check if user is a member of this band

public function hasAdmin(User $user): bool
// Check if user has admin role in this band

public function getUserRole(User $user): ?string
// Get user's role in band ('owner', 'admin', 'member', null)

public function getUserPosition(User $user): ?string
// Get user's position in band ('guitarist', 'vocalist', etc.)

public function removeMember(User $user): void
// Remove user from band

public function updateMemberRole(User $user, string $role): void
// Update member's role in band

public function updateMemberPosition(User $user, ?string $position): void
// Update member's position in band

// Tag Management
public function getGenresAttribute()
// Get band's genre tags

public function getInfluencesAttribute()  
// Get band's influence tags

// Media Management
public function getAvatarUrlAttribute()
// Get band avatar/photo URL

public function getAvatarThumbUrlAttribute()
// Get thumbnail avatar URL

public function registerMediaConversions(?Media $media = null): void
// Configure image conversions (150x150 thumbnails)

// Query Scopes
public static function withTouringBands()
// Include touring bands (bypasses OwnedBandsScope)
```

### BandService API (Referenced by deprecated methods)

#### Member Invitation & Management
```php
public function addMember(BandProfile $band, User $user, string $role = 'member', ?string $position = null): void
// Add user directly to band as active member

public function inviteMember(BandProfile $band, User $user, string $role = 'member', ?string $position = null): void  
// Send invitation to user to join band

public function acceptInvitation(BandProfile $band, User $user): void
// Accept pending invitation and become active member

public function declineInvitation(BandProfile $band, User $user): void
// Decline invitation and remove from pending

public function hasInvitedUser(BandProfile $band, User $user): bool
// Check if user has pending invitation
```

### Pivot Table Schema (band_profile_members)
```php
// Pivot table fields
public int $user_id;                   // Member user ID
public int $band_profile_id;           // Band ID
public string $role;                   // 'admin', 'member'
public ?string $position;              // 'guitarist', 'vocalist', 'drummer', etc.
public ?string $name;                  // Display name for member
public string $status;                 // 'active', 'invited'
public ?datetime $invited_at;          // Invitation timestamp
public timestamp $created_at;          // Relationship created
public timestamp $updated_at;          // Last updated
```

## Usage Examples

### Creating and Managing Bands
```php
// Create a new band
$owner = User::find(1);
$band = BandProfile::create([
    'name' => 'The Collective',
    'bio' => 'Alternative rock band from Corvallis',
    'owner_id' => $owner->id,
    'visibility' => 'public'
]);

// Add genres and influences
$band->attachTag('Alternative Rock', 'genre');
$band->attachTag('Indie', 'genre');
$band->attachTag('Radiohead', 'influence');
```

### Member Management
```php
$bandService = new BandService();
$bandMember = User::find(2);

// Invite a user to join the band
$bandService->inviteMember($band, $bandMember, 'member', 'guitarist');

// Check invitation status
if ($band->hasInvitedUser($bandMember)) {
    // User has pending invitation
    $bandService->acceptInvitation($band, $bandMember);
}

// Update member role/position
$band->updateMemberRole($bandMember, 'admin');
$band->updateMemberPosition($bandMember, 'lead guitarist');

// Check member status
if ($band->hasMember($bandMember)) {
    $role = $band->getUserRole($bandMember);
    $position = $band->getUserPosition($bandMember);
}
```

### Band Discovery and Administration
```php
// Get all bands owned by user
$myBands = $user->ownedBands;

// Get bands user is a member of
$memberBands = $user->bandProfiles;

// Get active members only
$activeMembers = $band->activeMembers;

// Get pending invitations
$pendingInvites = $band->pendingInvitations;

// Remove a member
$band->removeMember($formerMember);
```

### Media Management
```php
// Upload band photo
$band->addMediaFromRequest('photo')
      ->toMediaCollection('avatar');

// Get band avatar
$avatarUrl = $band->avatar_url;
$thumbUrl = $band->avatar_thumb_url;
```

## Integration Points

- **Member Profiles**: Band members linked to individual member profiles
- **Production System**: Bands can be attached as performers to shows/events
- **User System**: Band ownership and membership tied to user accounts
- **Media Library**: Band photos and promotional materials via Spatie MediaLibrary
- **Tagging System**: Genres and influences via Spatie Tags package
- **Global Scopes**: OwnedBandsScope filters out touring bands by default
- **Filament Admin**: Band management through admin interface
- **Permission System**: Role-based access control for band administration

## Business Rules

- Each band has exactly one owner who cannot be removed
- Owners have full administrative privileges over their bands
- Members can have 'admin' or 'member' roles with different permissions
- Invitations must be explicitly accepted or declined
- Band visibility controls who can see the band profile
- Global scope hides touring bands unless explicitly requested
- Members can belong to multiple bands simultaneously
- Position names are free-form text (guitarist, vocalist, drummer, etc.)