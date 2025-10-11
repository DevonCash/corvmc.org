# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This is a Laravel 12 application for the Corvallis Music Collective, a nonprofit organization operating out of Corvallis, Oregon. The codebase uses **Filament PHP v4** for admin interfaces and follows an **Action-based architecture** using `lorisleiva/laravel-actions` instead of traditional services.

## Core Technology Stack

- **Laravel 12** with **Filament v4** admin panels
- **Action-based architecture**: `lorisleiva/laravel-actions` for business logic
- **Spatie ecosystem**: permissions, tags, media library, model flags, periods, data DTOs
- **Pest testing** with SQLite in-memory database
- **Vite + TailwindCSS v4** with DaisyUI components
- **Stripe** (Laravel Cashier for reservations) + **Zeffy** (membership/donation webhooks pending)

## Development Commands

### Running the Development Environment
```bash
composer dev  # Runs server, queue, logs, and vite concurrently
```

### Testing
```bash
composer test              # Run parallel test suite
composer test:coverage     # Run tests with Xdebug coverage
vendor/bin/pest            # Run single test file
vendor/bin/pest --filter TestName  # Run specific test
```

### Building Assets
```bash
npm run dev    # Development server with hot reload
npm run build  # Production build
```

### Common Artisan Commands
```bash
php artisan migrate        # Run migrations
php artisan db:seed        # Seed database
php artisan queue:work     # Process queue jobs
php artisan filament:upgrade  # Upgrade Filament resources
```

## Architecture & Patterns

### Action-Based Business Logic

**All business logic is implemented using Actions** (`lorisleiva/laravel-actions`), not traditional services or controllers. Actions are located in `app/Actions/` organized by domain:

```
app/Actions/
├── Bands/                 # Band management, member invitations
├── Cache/                 # Cache warming and invalidation
├── Credits/               # Credit system for free hours
├── Equipment/             # Equipment loans and management
├── MemberProfiles/        # Member directory and profiles
├── Payments/              # Payment calculations and processing
├── Productions/           # Event/show management
├── Reservations/          # Practice space booking and conflicts
└── [other domains]/
```

**Key Action patterns:**
- Use `AsAction` trait
- Return strongly-typed results
- Keep Actions focused on single responsibility
- Can be called as: `ActionName::run($params)` or `ActionName::dispatch($params)` (for queued)

**Example Action usage:**
```php
// In controllers, Filament resources, or other Actions
$reservation = CreateReservation::run($user, $startTime, $endTime);
$cost = CalculateReservationCost::run($user, $startTime, $endTime);
```

### Filament Resource Organization

Filament resources follow a strict directory structure:

```
app/Filament/Resources/
├── ModelResource.php           # Main resource class
└── ModelResource/
    ├── Pages/                  # CRUD pages
    ├── Schemas/               # Form/infolist schemas
    └── Tables/                # Table configurations
```

**Filament Panels:**
- Member panel at `/member` (amber theme)
- Admin functions integrated into member panel with role-based access
- Resources auto-discovered from `app/Filament/Resources/`

### Model Architecture

**Key models with important relationships:**
- `User` → `MemberProfile` (one-to-one extended profiles)
- `Band` → `BandMember` (many-to-many with roles: owner, admin, member)
- `Production` → polymorphic lineup with `Band` performers
- `Reservation` (base class) → STI with `RehearsalReservation` and `ProductionReservation`
- `Reservation` → polymorphic `reserver` (User, Production, Band, etc.)

**Common Spatie traits:**
```php
use HasRoles;           // Role-based permissions
use HasTags;            // Genre/skill tagging
use InteractsWithMedia; // Avatar/media uploads
use HasFlags;           // Feature flags (e.g., is_teacher, is_professional)
```

### Data Transfer Objects

The codebase uses `spatie/laravel-data` DTOs for complex data structures:
- `VenueLocationData` - Production venue information
- Cost calculation responses from Payment Actions

### Single Table Inheritance (STI)

The `Reservation` model uses STI with a `type` column:
- Base class: `Reservation`
- Subclasses: `RehearsalReservation`, `ProductionReservation`
- Automatic type assignment on creation

## Business Logic Domains

### Membership System
- **Roles**: member, sustaining member, staff, admin (via `spatie/laravel-permission`)
- **Sustaining members**: Users with $10+ monthly donations or manually assigned role
- **Benefits**: 4 free practice hours/month with credits system
- **Detection**: Pending Zeffy webhook integration

### Practice Space Reservations
- **Pricing**: $15/hour base rate
- **Free hours**: Tracked via credits system (`user_credits` table)
- **Business hours**: 9 AM - 10 PM
- **Duration**: 1-8 hour blocks
- **Conflict detection**: Uses `spatie/period` to check against other reservations and productions
- **Payment**: Stripe checkout with optional fee coverage
- **Recurring**: Sustaining members can create recurring reservations

### Productions (Events/Shows)
- **Lifecycle**: pre-production → published → completed/cancelled (via `spatie/laravel-model-states`)
- **Public routes**: `/events`, `/events/{production}`, `/show-tonight`
- **Conflict detection**: Automatically checks for practice space conflicts
- **Manager permissions**: Only production managers can edit their productions
- **Performers**: Many-to-many with `Band`, includes set order and duration

### Member Directory
- **Public directory**: `/members` route with search/filtering
- **Visibility**: public, members-only, or private profiles
- **Skills/genres**: Tagged via `spatie/laravel-tags`
- **Flags**: `is_teacher`, `is_professional` for directory filtering

### Band Profiles
- **Public directory**: `/bands` route
- **Member roles**: owner, admin, member (pivot table with roles)
- **Invitations**: Email-based invitation system for adding members
- **EPK**: Planned media management for Electronic Press Kits

### Equipment Management
- **Equipment loans**: Checkout/return system with condition tracking
- **Damage reports**: Incident reporting for equipment issues
- **Reservations**: Equipment can be reserved alongside practice space

## Database Conventions

### Money Handling
**CRITICAL**: All monetary values are stored as **integers in cents**:
```php
// $15.00 is stored as 1500
protected function casts(): array {
    return [
        'cost' => MoneyCast::class . ':USD',
    ];
}
```

### Timestamps
- All models use `created_at`, `updated_at`
- Many use soft deletes (`deleted_at`)
- Reservations use `reserved_at`, `reserved_until` for time slots

### Indexes
Performance indexes on:
- Reservation `reserved_at`, `reserved_until`, `status`
- Production `start_time`, `published_at`, `status`
- User `email` (unique)

## Testing Patterns

### Test Organization
- **Feature tests**: End-to-end workflows in `tests/Feature/`
- **Unit tests**: Action and model tests in `tests/Unit/`
- Uses Pest with `RefreshDatabase` trait
- In-memory SQLite for fast test execution

### Writing Tests
```php
// Feature test example
it('creates a reservation with correct pricing', function () {
    $user = User::factory()->create();
    $start = now()->addDay()->setHour(10);
    $end = $start->copy()->addHours(2);

    $reservation = CreateReservation::run($user, $start, $end);

    expect($reservation->cost)->toBe(3000); // $30.00 in cents
});
```

## Public Routes Structure

```
/                      # Homepage with upcoming events
/events                # Event listing with search/filtering
/events/{production}   # Individual event detail page
/show-tonight          # Redirect to next/current show
/members               # Member directory
/members/{profile}     # Individual member profile
/bands                 # Band directory
/bands/{band}          # Individual band profile
/equipment             # Equipment catalog
/about                 # Staff and board information
/contribute            # Donation/support page
/sponsors              # Sponsor listing
```

## Configuration & Environment

### Key Environment Variables
- `STRIPE_KEY`, `STRIPE_SECRET` - Stripe payment processing
- `AWS_*` - S3 for media storage (via `spatie/laravel-medialibrary`)
- `MAIL_*` - PostMark for transactional emails
- Database: PostgreSQL in production, SQLite for testing

### Important Config Files
- `config/reservation.php` - Practice space business rules
- `config/permission.php` - Role/permission settings
- `config/media-library.php` - Media upload/processing settings

## Common Development Tasks

### Adding a New Feature Domain
1. Create Actions in `app/Actions/NewDomain/`
2. Create models with appropriate Spatie traits
3. Create Filament resource with Schemas/ and Tables/ subdirectories
4. Add routes in `routes/web.php` if public-facing
5. Write feature tests covering main workflows

### Modifying Business Logic
1. Locate relevant Action in `app/Actions/`
2. Update Action's `handle()` method
3. Update related tests
4. Run test suite to ensure no regressions

### Adding a New Filament Resource
1. Create resource: `php artisan make:filament-resource ModelName`
2. Organize into subdirectories: `Pages/`, `Schemas/`, `Tables/`
3. Extract form schema to `Schemas/ModelNameForm.php`
4. Extract table config to `Tables/ModelNameTable.php`
5. Add policies for permission checks

## Key Packages & Their Uses

- **filament/filament** - Admin panel framework
- **lorisleiva/laravel-actions** - Business logic organization
- **spatie/laravel-permission** - Role-based access control
- **spatie/laravel-tags** - Tagging system for genres/skills
- **spatie/laravel-medialibrary** - File uploads (avatars, posters, EPKs)
- **spatie/laravel-data** - DTOs and type-safe data objects
- **spatie/period** - Time period calculations for conflict detection
- **spatie/laravel-model-flags** - Boolean feature flags on models
- **laravel/cashier** - Stripe integration for reservations
- **pestphp/pest** - Testing framework
- **guava/calendar** - Calendar widget in Filament
