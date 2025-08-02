# MemberProfileService

The MemberProfileService encapsulates business logic for managing member profiles, directory operations, and collaboration features within the Corvallis Music Collective platform.

## Purpose & Responsibilities

The MemberProfileService serves as the central business logic layer for all member profile operations, abstracting complex directory management tasks from controllers and providing a clean API for profile manipulation. It handles profile updates, search and discovery features, collaboration matching, and directory statistics generation. The service ensures data consistency, enforces business rules, and provides a single point of control for all profile-related operations across the application.

This service is particularly valuable for maintaining clean separation of concerns between the presentation layer (Filament forms, API endpoints) and the data layer (Eloquent models). It encapsulates complex query logic for member discovery, handles the intricacies of the visibility system, and provides optimized methods for common operations like searching by skills or generating directory statistics. The service also manages the interaction between multiple systems - coordinating between the tagging system for skills/genres, the flag system for directory preferences, and the permission system for visibility controls.

## API Reference

### Profile Management

```php
public function updateVisibility(MemberProfile $profile, string $visibility): bool
```
Updates profile visibility setting with validation.
- **Parameters**: `$profile` - target profile, `$visibility` - 'public', 'members', or 'private'
- **Returns**: `true` on success, `false` for invalid visibility values
- **Business Logic**: Validates visibility value against allowed options before updating

```php
public function updateSkills(MemberProfile $profile, array $skills): bool
```
Replaces all profile skills with new array.
- **Parameters**: `$profile` - target profile, `$skills` - array of skill names
- **Returns**: `true` on successful update
- **Business Logic**: Detaches all existing skill tags and attaches new ones atomically

```php
public function updateGenres(MemberProfile $profile, array $genres): bool
```
Replaces all profile genres with new array.
- **Parameters**: `$profile` - target profile, `$genres` - array of genre names
- **Returns**: `true` on successful update
- **Business Logic**: Manages genre tags separately from skills using type-specific tagging

```php
public function updateInfluences(MemberProfile $profile, array $influences): bool
```
Replaces all profile influences with new array.
- **Parameters**: `$profile` - target profile, `$influences` - array of influence names
- **Returns**: `true` on successful update
- **Business Logic**: Handles influence tags as a separate category for musical inspirations

```php
public function setFlags(MemberProfile $profile, array $flags): bool
```
Replaces all profile directory flags with new array.
- **Parameters**: `$profile` - target profile, `$flags` - array of flag names
- **Returns**: `true` on successful update
- **Business Logic**: Manages directory flags like 'seeking_band', 'available_for_session' for discovery

### Search & Discovery

```php
public function searchProfiles(
    ?string $query = null,
    ?array $skills = null,
    ?array $genres = null,
    ?array $flags = null,
    ?User $viewingUser = null
): Collection
```
Comprehensive profile search with multiple filter options.
- **Parameters**: 
  - `$query` - search term for name or bio
  - `$skills` - array of required skills
  - `$genres` - array of required genres  
  - `$flags` - array of required directory flags
  - `$viewingUser` - user performing search (affects visibility)
- **Returns**: Collection of matching MemberProfile models
- **Business Logic**: 
  - Bypasses global visibility scope to apply custom visibility logic
  - Supports partial matching on skills/genres (any match)
  - Respects user visibility preferences and permissions
  - Limits results to 50 profiles for performance

```php
public function suggestCollaborators(MemberProfile $profile): Collection
```
Find potential collaborators based on shared musical interests.
- **Parameters**: `$profile` - profile to find collaborators for
- **Returns**: Collection of suggested MemberProfile models
- **Business Logic**:
  - Matches profiles with shared genres or influences
  - Excludes the requesting profile from results
  - Only includes public and members-visible profiles
  - Prioritizes profiles with collaboration flags

```php
public function getProfilesWithFlag(string $flag): Collection
```
Get all profiles with a specific directory flag.
- **Parameters**: `$flag` - flag name to filter by
- **Returns**: Collection of flagged profiles
- **Business Logic**: Respects visibility settings and includes user relationships

### Statistics & Analytics

```php
public function getDirectoryStats(): array
```
Generate comprehensive directory statistics.
- **Returns**: Array with directory metrics:
  - `total_members` - total profile count
  - `public_profiles` - publicly visible profiles
  - `seeking_bands` - profiles with seeking_band flag
  - `available_for_session` - profiles available for session work
  - `top_skills` - most popular skills with counts
  - `top_genres` - most popular genres with counts
- **Business Logic**: Aggregates data across multiple collections and provides insights for admin dashboards

```php
protected function getTopTags(string $type, int $limit = 10): array
```
Get most popular tags by type (internal helper method).
- **Parameters**: `$type` - tag type ('skill', 'genre', 'influence'), `$limit` - maximum results
- **Returns**: Array of tag names with usage counts
- **Business Logic**: Queries tag usage across all profiles and ranks by popularity

## Usage Examples

### Profile Updates
```php
$service = new MemberProfileService();
$profile = MemberProfile::find(1);

// Update profile skills
$service->updateSkills($profile, ['guitar', 'vocals', 'songwriting']);

// Set directory flags
$service->setFlags($profile, ['seeking_band', 'open_to_collaboration']);

// Update visibility
$service->updateVisibility($profile, 'members');
```

### Member Discovery
```php
// Find guitarists open to collaboration
$collaborators = $service->searchProfiles(
    skills: ['guitar'],
    flags: ['open_to_collaboration']
);

// Search for jazz musicians  
$jazzMusicians = $service->searchProfiles(
    genres: ['jazz'],
    viewingUser: $currentUser
);

// Get collaboration suggestions
$suggestions = $service->suggestCollaborators($myProfile);
```

### Directory Administration
```php
// Get directory statistics
$stats = $service->getDirectoryStats();
echo "Total members: " . $stats['total_members'];
echo "Seeking bands: " . $stats['seeking_bands'];

// Find members by specific needs
$sessionPlayers = $service->getProfilesWithFlag('available_for_session');
$teacherProfiles = $service->getProfilesWithFlag('music_teacher');
```

## Integration Points

- **Member Profile Model**: Direct manipulation of profile data and relationships
- **User Model**: Visibility checks and permission integration
- **Spatie Tags**: Skills, genres, and influences management
- **Spatie ModelFlags**: Directory flags and member preferences
- **Global Scopes**: Bypasses MemberVisibilityScope for custom visibility logic
- **Permission System**: Respects 'view private member profiles' permission
- **Filament Admin**: Provides backend services for admin interface operations

## Business Rules

- **Visibility Enforcement**: Public < Members < Private hierarchy with owner override
- **Tag Management**: Skills, genres, and influences maintained as separate tag types
- **Search Limitations**: Results limited to 50 profiles for performance
- **Collaboration Matching**: Based on shared genres/influences, excludes requester
- **Statistics Accuracy**: Real-time calculation of directory metrics
- **Permission Integration**: Staff can view private profiles with appropriate permissions