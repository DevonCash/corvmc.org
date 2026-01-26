# CLAUDE.md

This file provides guidance to Claude Code when working with this repository.

## Overview

Laravel 12 application for the Corvallis Music Collective, a nonprofit in Corvallis, Oregon. Uses **Filament v4** admin panels and **Action-based architecture** (`lorisleiva/laravel-actions`) instead of traditional services.

## Core Technology Stack

- **Laravel 12** with **Filament v4** admin panels
- **Action-based architecture**: `lorisleiva/laravel-actions` for business logic
- **Spatie ecosystem**: permissions, tags, media library, model flags, periods, data DTOs
- **Pest testing** with SQLite in-memory database
- **Vite + TailwindCSS v4** with DaisyUI components
- **Stripe** (Laravel Cashier) for payments

## Development Commands

```bash
composer dev               # Runs server, queue, logs, and vite concurrently
composer test              # Run parallel test suite
composer test:coverage     # Run tests with Xdebug coverage
npm run dev                # Development server with hot reload
npm run build              # Production build
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

**Key patterns:**

- Use `AsAction` trait, return strongly-typed results, single responsibility
- Call as: `ActionName::run($params)` or `ActionName::dispatch($params)` (queued)

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

**Filament Panel:** Single member panel at `/member` (amber theme) with role-based admin access

### Filament Actions in Livewire Components

When embedding Filament actions in Livewire components (Pages, Widgets, etc.), follow these requirements:

**Required traits and interfaces:**

```php
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;

class MyPage extends Page implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
```

**Action method requirements:**

- Method name must match the action name or action name + "Action"
- **CRITICAL: Must have explicit `: Action` return type** (without it, Livewire cannot find the action)
- Action name in `Action::make()` must match the method name

```php
// ✅ CORRECT - explicit return type
public function modifyMembershipAmountAction(): Action
{
    return ModifyMembershipAmountAction::make();
}

// ❌ WRONG - missing return type causes "Property not found" error
public function modifyMembershipAmountAction()
{
    return ModifyMembershipAmountAction::make();
}
```

**Rendering in Blade:**

```blade
{{ $this->modifyMembershipAmountAction }}
```

**Note:** `<x-filament-actions::modals />` is already included in `<x-filament-panels::page>`.

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

Uses `spatie/laravel-data` DTOs: `VenueLocationData`, cost calculation responses

### Single Table Inheritance

`Reservation` model uses STI with `type` column: `RehearsalReservation`, `ProductionReservation`

## Business Logic Domains

### Membership System

- **Roles**: member, sustaining member, staff, admin (via `spatie/laravel-permission`)
- **Sustaining members**: Manually assigned role by administrators
- **Benefits**: 4 free practice hours/month with credits system

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

- Public directory at `/bands`
- Member roles: owner, admin, member (pivot table)
- Email-based invitation system

### Equipment Management

- Checkout/return system with condition tracking
- Damage reports for equipment issues

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

### Timestamps & Conventions

- Standard Laravel timestamps: `created_at`, `updated_at`
- Many models use soft deletes
- Reservations: `reserved_at`, `reserved_until` for time slots

### Time Handling

**App Timezone**: `America/Los_Angeles` (PST/PDT)  
**Database**: PostgreSQL stores timestamps in UTC internally  
**Model Casts**: Automatically convert to/from app timezone

**CRITICAL Best Practices:**

✅ **DO:**

- Trust model casts - `$record->reserved_at` is already a Carbon instance
- Check instance type before parsing:

  ```php
  $reservedAt = $data['reserved_at'] instanceof Carbon
      ? $data['reserved_at']
      : Carbon::parse($data['reserved_at'], config('app.timezone'));
  ```

- Use `now()` and `today()` helpers (automatically use app timezone)
- Specify timezone when creating from strings:

  ```php
  Carbon::parse('2024-10-11 14:00', config('app.timezone'));
  ```

❌ **DON'T:**

- Re-parse Carbon instances: `Carbon::parse($record->reserved_at)` is wrong
- Create datetimes without timezone: `Carbon::parse('2024-10-11 14:00')` uses UTC!
- Use `toDateString()` then re-parse (loses time and timezone info)

**Historical Note:** A migration added 8 hours to all timestamps when switching from UTC to PST/PDT.

## Testing

Uses **Pest** with `RefreshDatabase` trait and in-memory SQLite. Tests in `tests/Feature/` and `tests/Unit/`.

## Public Routes

`/` (homepage), `/events`, `/events/{production}`, `/show-tonight`, `/members`, `/members/{profile}`, `/bands`, `/bands/{band}`, `/equipment`, `/about`, `/contribute`, `/sponsors`

## Key Packages

- **filament/filament** - Admin panels
- **lorisleiva/laravel-actions** - Business logic
- **spatie/laravel-permission** - Role-based access
- **spatie/laravel-tags** - Genre/skill tagging
- **spatie/laravel-medialibrary** - File uploads
- **spatie/laravel-data** - DTOs
- **spatie/period** - Conflict detection
- **spatie/laravel-model-flags** - Feature flags
- **laravel/cashier** - Stripe integration

## PHPStan Type Safety (Level 2)

### Type Hierarchy

1. Specific model classes (`User`, `Event`)
2. Interfaces for polymorphic cases (`Reportable`)
3. Type checks with `instanceof`
4. `@property` annotations for dynamic properties
5. `@var` type hints for query results
6. Avoid generic `Model` types

### Key Patterns

**Static Property Pattern:**

```php
// In trait - define default
protected static string $creatorForeignKey = 'user_id';

// In model - override when needed
protected static string $creatorForeignKey = 'organizer_id';
```

**@property Annotations:**

- Enum casts: `@property PaymentStatus $payment_status`
- Relationships: `@property-read User|null $organizer`
- Aggregate counts: `@property-read int|null $loans_count`

**Typed Closure Parameters:**

```php
->map(fn (\App\Models\Equipment $item) => $item->name)
```

**PHPStan Ignores for Framework Magic:**

```php
/** @phpstan-ignore method.notFound */
return $query->public();
```

### Conventions

- Ask before adding `@phpstan-ignore` annotations
- Use `getKey()` instead of `->id` for interface types
- Prefer interfaces over generic `Model` types
- Use `method_exists()` checks for trait methods on generic types

## Module Architecture

The application uses `internachi/modular` for domain organization with two distinct layers. See [docs/module-architecture.md](docs/module-architecture.md) for full details.

**Module layer** (`app-modules/`): Self-contained domains owning their models, actions, and business logic. Modules: Events, SpaceManagement, Finance, Bands, Equipment, Moderation, MemberProfiles, Support.

**Integration layer** (`app/`): Coordinates between modules. Contains policies, listeners, observers, and integration models that bridge modules.

**Key principles:**
- Modules communicate via domain events and interfaces, not direct references
- Side effects (cache, notifications) handled by listeners in integration layer
- Models expose helpers (`isOrganizedBy()`) but no authorization logic

## Authorization & Policies

Policies live in `app/Policies/` and use role-based authorization. See [docs/authorization.md](docs/authorization.md) for full details.

**Core principles:**
- Integration layer owns authorization (policies in `app/Policies/`)
- Modules own domain knowledge (models expose `isOrganizedBy()`, `isOwnedBy()`)
- Use roles + context, not permission strings (`hasRole()` + model helpers)
- Domain verbs, not just CRUD (`publish`, `cancel`, `reschedule`)

**Manager roles:** `production manager` (Events), `practice space manager` (Reservations)
