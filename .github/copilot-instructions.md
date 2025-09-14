# Corvallis Music Collective - AI Coding Agent Instructions

## Architecture Overview

This is a Laravel 12 application built for a nonprofit music collective with a **service-layer architecture** and **comprehensive caching strategy**. The codebase uses **Filament PHP v4** for admin interfaces and follows specific patterns for maintainability.

### Core Tech Stack
- **Laravel 12** with **Filament v4** (admin panels at `/member` path)
- **Spatie ecosystem**: permissions, tags, media library, model flags, periods, data objects
- **Vite + TailwindCSS v4** with DaisyUI components
- **Pest testing** with SQLite in-memory database
- **Stripe** (reservations) + **Zeffy webhooks** (memberships/donations)

## Critical Service Layer Pattern

All business logic lives in dedicated services registered as **singletons** in `AppServiceProvider`:

```php
// Key services - always use these for business logic
ReservationService::class     // Practice space booking, pricing, conflicts
UserSubscriptionService::class // Membership status, sustaining member logic  
ProductionService::class      // Event management, conflict detection
BandService::class           // Band member management, invitations
CacheService::class          // Performance optimization
```

**Never put complex business logic in controllers, models, or Filament resources.** Use service methods with clear return types and comprehensive error handling.

## Development Workflow Commands

```bash
# Development environment (preferred)
composer dev  # Runs server, queue, logs, vite concurrently

# Testing with coverage
composer test              # Standard test suite
composer test:coverage     # With Xdebug coverage
php artisan test:coverage  # Alternative coverage command

# Custom test commands (comprehensive business logic testing)
php artisan test:invitations --clean --send    # User invitation system
php artisan test:notifications --send         # All notification types

# Cache management (critical for performance)
php artisan cache:manage warm                 # Warm up caches
php artisan cache:manage clear-user --user=123 # Clear user-specific caches
php artisan cache:manage stats                # Cache performance stats
```

## Filament Resource Organization

Resources follow a **strict directory structure** with separated concerns:

```
app/Filament/Resources/
├── ModelResource.php           # Main resource
├── ModelResource/
    ├── Pages/                  # CRUD pages
    ├── Schemas/               # Form/infolist schemas
    └── Tables/                # Table configurations
```

**Key patterns:**
- Form schemas separated from table configs
- Use `RelationManager` classes for related data
- Resource auto-discovery from `app/Filament/Resources`
- Single panel at `/member` with amber theme

## Caching Strategy (Performance Critical)

The app implements **aggressive caching** with automatic invalidation:

### Cache Layers & TTLs
- User data: 30-60 min TTL (sustaining status, free hours)
- Dashboard widgets: 5-10 min TTL
- Member directory: 1 hour TTL with tag invalidation
- Reservation conflicts: 30 min TTL
- Subscription stats: 30 min TTL

### Model Observers Pattern
```php
// Observers automatically clear related caches
UserObserver::class        // Clears user stats, activity
ReservationObserver::class // Clears conflicts, user hours
ProductionObserver::class  // Clears events, conflicts
TagObserver::class         // Clears member directory filters
```

## Business Logic Patterns

### Membership System
- **Sustaining members**: $10+ monthly donations OR role assignment
- **Free hours**: 4 hours/month for sustaining members with rollover
- **Detection logic**: `UserSubscriptionService::isSustainingMember()`

### Practice Space Reservations
- **Pricing**: $15/hour base, free hours for sustaining members
- **Conflicts**: Productions and other reservations using `spatie/period`
- **Payments**: Stripe checkout with fee coverage options
- **Business hours**: 9 AM - 10 PM, 1-8 hour durations

### Production Management
- **Lifecycle**: pre-production → published → completed/cancelled
- **Conflicts**: Automatic detection with reservation system
- **Public routes**: `/events`, `/events/{production}`, `/show-tonight`

## Testing Patterns

### Custom Test Commands
When building complex features, create dedicated test commands:

```php
protected $signature = 'test:feature {--dry-run} {--clean} {--send}';

// Structure: Clear sections with emojis, comprehensive testing
$this->info('🧪 Testing Feature Name');
$this->line('========================');
// Test happy path, error conditions, edge cases, cleanup
```

### Test Organization
- **Feature tests**: End-to-end workflows in `tests/Feature/`
- **Unit tests**: Service layer methods in `tests/Unit/`
- **Coverage tracking**: Use `composer test:coverage` regularly
- **Story coverage**: `php artisan check:story-coverage` validates against user stories

## Database & Model Conventions

### Spatie Traits Usage
```php
// Standard model pattern
use HasRoles, HasTags, InteractsWithMedia;
// Descriptive migration timestamps: 2025_07_30_*
// Soft deletes + proper foreign keys
```

### Key Models
- `User` → `MemberProfile` (extended profiles)
- `Band` → `BandMember` (pivot with roles)  
- `Production` → polymorphic with `Band` lineups
- `Reservation` → polymorphic with `Transaction`

## Integration Points

### Payment Processing
- **Stripe**: Practice space reservations via Laravel Cashier
- **Zeffy**: Donations/memberships via webhook (transaction model ready)

### External APIs
- **GitHub API**: Via `GitHubService` for repository integration
- **Email**: PostMark for transactional emails
- **Media**: S3 for production file storage

## Development Best Practices

1. **Always use services** for business logic - never in controllers/resources
2. **Cache invalidation**: Let observers handle it automatically  
3. **Test commands**: Create comprehensive test commands for complex features
4. **Resource organization**: Follow the established directory structure
5. **Performance**: Monitor cache hit rates with `cache:manage stats`

## Key File Locations

- Services: `app/Services/` (singleton pattern)
- Filament resources: `app/Filament/Resources/` with subdirectories
- Public routes: `routes/web.php` (clean, SEO-friendly URLs)
- Cache management: `app/Console/Commands/CacheManagement.php`
- Business config: `app/Settings/` (Spatie settings)
