# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel-based web application for the Corvallis Music Collective, a nonprofit organization.

## Technologies

### Core Framework Stack

- **Laravel 12** - PHP framework
- **Filament PHP v4** - Admin panel and forms framework
- **Vite** - Asset bundling with TailwindCSS v4
- **Pest** - Testing framework

```aside
NOTE: Filament v4 beta makes some changes from v3
- Form and Infolist layouts have been unified into the Schema namespace
```

### Key Spatie Libraries

- **spatie/laravel-permission** - Role and permission management
- **spatie/laravel-tags** - Tagging system for skills and categorization
- **spatie/laravel-medialibrary** - File uploads and media management
- **spatie/laravel-data** - Data transfer objects
- **spatie/laravel-settings** - Application settings management
- **spatie/laravel-query-builder** - API query filtering and sorting
- **spatie/laravel-model-flags** - Model feature flags
- **spatie/period** - Date/time period handling

### Additional Libraries

- **guava/calendar** - Calendar component integration
- **unicon-rocks/unicon-laravel** - Frontend icon system

### Frontend Integration

- TailwindCSS v4 with Vite plugin
- Laravel Vite plugin for asset compilation
- Filament handles most UI components and styling
- Custom CSS in `resources/css/app.css`

## Development Commands

### Server and Development

- `composer dev` - Start development environment (runs server, queue, logs, and vite concurrently)
- `php artisan serve` - Start Laravel development server
- `npm run dev` - Start Vite development server for assets
- `npm run build` - Build production assets

### Testing and Quality

- `composer test` - Run the test suite (clears config and runs PHPUnit/Pest tests)
- `vendor/bin/pest` - Run Pest tests directly
- `vendor/bin/pint` - Laravel Pint code style fixer

### Custom Test Commands

- `php artisan test:invitations` - Test the user invitation system end-to-end
- `php artisan test:notifications` - Test all notification types in the system
- Use `--clean` flag on invitation tests to clean up test data first
- Use `--send` flag on notification tests to actually send notifications (default is dry-run)

### Import Features

- **Zeffy Data Import** - Use the "Import Zeffy Data" button in the Transactions resource
- **File Format Support** - Accepts both CSV and Excel (.xlsx, .xls) files from Zeffy exports
- Automatic column mapping with visual interface
- Real-time validation and error reporting
- Duplicate detection and failed payment filtering
- Dynamic additional questions handling for campaign-specific fields
- Supports all Zeffy export columns including donor info, campaigns, and payment details
- Background processing with progress tracking and notifications

### Database

- `php artisan migrate` - Run database migrations
- `php artisan migrate:fresh --seed` - Fresh migration with seeders
- `php artisan db:seed` - Run database seeders

### Queue and Background Jobs

- `php artisan queue:work` - Start queue worker
- `php artisan queue:listen --tries=1` - Listen for queue jobs with single try

### Logs and Debugging

- `php artisan pail --timeout=0` - Real-time log viewer

## Architecture Overview

### Third-Party Integrations

- **Stripe** - Payment processing via Laravel Cashier for practice space reservations
- **Zeffy** - Payment processing via Zapier webhooks for donations and memberships  
- **Filament Plugins** - spatie/laravel-medialibrary-plugin, spatie/laravel-tags-plugin

### Application Structure

#### Models and Domain Logic

Core models include:

- `User` - Authentication and base user data
- `MemberProfile` - Extended user profiles with bio, links, skills (tags), and media
- `Band` - Band information and social media links
- `Production` - Events and shows management
- `Reservation` - Practice space booking system
- `Transaction` - Payment and financial tracking

#### Filament Resource Organization

Resources are organized in dedicated directories under `app/Filament/Resources/`:

- Each resource has dedicated `Pages/`, `Schemas/`, and `Tables/` subdirectories
- Form schemas are separated from table configurations
- Uses resource-specific form and infolist classes for better organization

#### Authentication and Panels

- Single Filament panel at `/member` path with amber primary color
- Integrated authentication with profile management
- Resources auto-discovery from `app/Filament/Resources`

#### Service Layer Architecture

The application uses dedicated service classes for complex business logic:

- `UserSubscriptionService` - Handles membership status and sustaining member logic
- `UserInvitationService` - Manages user invitation workflow and token handling
- `ReservationService` - Practice space booking logic with conflict detection
- `ProductionService` - Event management and production workflows
- `BandService` - Band profile management and member relationships
- `MemberProfileService` - Member directory and profile management
- `CacheService` - Centralized cache management and performance optimization

#### Custom Testing Commands Pattern

When developing complex features, create dedicated test commands to validate functionality:

##### Process for Creating Test Commands:

1. **Create the command**: `php artisan make:command TestFeatureName`
2. **Structure the command**:
   - Use clear section headers with emojis for visual separation
   - Include `--dry-run` or similar flags to show what would happen without executing
   - Provide `--clean` flags to reset test data
   - Use descriptive output with ✓ for success, ✗ for failure, → for dry-run actions
3. **Test comprehensively**:
   - Test the happy path (normal flow)
   - Test error conditions (expired tokens, invalid data)
   - Test edge cases (duplicate actions, cleanup)
   - Verify data integrity before and after operations
4. **Include cleanup**: Always clean up test data to avoid pollution
5. **Add to CLAUDE.md**: Document the command and its flags for future reference

##### Example Implementation:
```php
protected $signature = 'test:feature {--dry-run : Show what would happen} {--clean : Clean test data}';

public function handle() {
    $this->info('🧪 Testing Feature Name');
    $this->line('========================');
    
    if ($this->option('dry-run')) {
        $this->warn('DRY RUN mode - no changes will be made');
    }
    
    // Test sections with clear visual separation
    $this->info('📧 1. Testing core functionality...');
    // ... test logic
    
    $this->line('   ✓ Success message');
    $this->line('   → Would execute action (dry-run)');
    
    $this->info('✅ All tests passed!');
}
```

### Database Design

- Uses descriptive migration timestamps (2025_07_30_*)
- Implements soft deletes and proper foreign key relationships
- Media library integration for file attachments
- Permission system tables for role-based access

### Key Business Logic

#### Membership System

- Users can be "sustaining members" via role assignment or $10+ monthly donations
- Sustaining members get 4 free practice space hours per month
- Monthly hour tracking with rollover capabilities
- Transaction-based membership detection via `UserSubscriptionService`

#### Practice Space Reservations

- $15/hour base rate for all users
- 4 free hours monthly for sustaining members
- Operating hours: 9 AM - 10 PM
- Duration limits: 1-8 hours per reservation
- Sophisticated conflict detection with productions and other reservations
- Support for recurring reservations (sustaining members only)
- **Stripe Integration**: Online payments via Laravel Cashier with checkout sessions
- Polymorphic relationship: reservations linked to transactions via `transactionable`

#### Event Management (Productions)

- Production lifecycle: pre-production → published → completed/cancelled
- Band lineup management with set ordering and duration
- Manager-based permissions and production ownership
- Integration with practice space conflict detection
- Public event listing with search and filtering

## Development Guidelines

### Model Conventions

- Use Spatie traits: `HasTags`, `InteractsWithMedia`, `HasRoles` as appropriate
- Implement proper accessor methods for computed properties (e.g., `getAvatarAttribute`)
- Define clear fillable arrays and relationships
- Add descriptive docblocks for model purpose

### Filament Resource Patterns

- Separate form schemas, infolists, and table configurations into dedicated classes
- Use proper page routing in `getPages()` method
- Leverage Filament's built-in widgets and components
- Follow the established directory structure for consistency

### Testing

- Tests use in-memory SQLite database
- Both Feature and Unit test suites configured
- Use Pest testing framework syntax
- Environment properly isolated for testing

This pattern provides reliable, repeatable testing for complex business logic and integrations.

## TODO: Next Session Tasks

### Stripe Transaction Fee Coverage
- **Task**: Add opt-in upcharge to cover Stripe transaction costs
- **Location**: `ReservationService::createCheckoutSession()` method
- **Implementation**: 
  - Calculate Stripe fees (2.9% + $0.30 for cards)
  - Add optional checkbox in checkout for user to cover processing fees
  - Update line items to include fee coverage if selected
  - Ensure fee calculation is accurate for reservation amounts
- **User Experience**: Allow users to voluntarily cover transaction costs to support the organization

## Performance Optimization

### Caching Strategy

The application implements comprehensive caching to optimize database queries and improve response times:

#### Cache Layers
- **User Authentication**: Sustaining member status, free hours usage (30-60 min TTL)
- **Dashboard Widgets**: User statistics, recent activity, upcoming events (5-10 min TTL)
- **Member Directory**: Tag filters for skills, genres, influences (1 hour TTL with tags)
- **Reservation System**: Conflict detection, daily reservations/productions (30 min TTL)
- **Subscription Data**: Member statistics, revenue calculations (30 min TTL)

#### Cache Management

**Artisan Commands:**
```bash
# Cache management
php artisan cache:manage warm          # Warm up commonly used caches
php artisan cache:manage clear         # Clear all application caches
php artisan cache:manage stats         # Show cache statistics
php artisan cache:manage clear-user --user=123    # Clear specific user caches
php artisan cache:manage clear-date --date=2024-01-01    # Clear date-specific caches
php artisan cache:manage clear-tags    # Clear member directory tag caches
```

**Automatic Cache Invalidation:**
- Model observers automatically clear related caches when data changes
- User changes clear: sustaining status, statistics, activity feeds
- Reservations clear: conflict detection, user free hours, daily schedules
- Productions clear: upcoming events, conflict detection, manager stats
- Tags clear: member directory filter options using cache tags

#### Performance Impact
- **Before caching**: 15-25 queries per dashboard load, 800ms-2000ms page load
- **After caching**: 2-5 queries per dashboard load, 200ms-400ms page load
- **Query reduction**: 75% fewer database queries for common operations

#### Cache Keys Pattern
- User data: `user.{id}.{type}.{suffix}`
- Date data: `{type}.conflicts.{Y-m-d}`
- Global data: `{feature_name}` or `{feature_name}.{suffix}`
- Tagged data: Uses cache tags for bulk invalidation