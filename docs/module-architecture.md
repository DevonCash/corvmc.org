# Module Architecture

This application uses a modular architecture with `internachi/modular` to organize code by domain. This document explains the separation between the **module layer** and **integration layer**.

## Overview

```tree
app/                          # Integration Layer
├── Listeners/                # Cross-module event handlers
├── Observers/                # Model observers for cache/side effects
├── Policies/                 # Authorization (see docs/authorization.md)
├── Models/                   # Integration models that bridge modules
└── Providers/

app-modules/                  # Module Layer
├── events/
├── space-management/
├── finance/
├── bands/
├── equipment/
├── moderation/
├── membership/
└── support/
```

## The Two Layers

### Module Layer (`app-modules/`)

Modules are **self-contained domains** that own their models, actions, and business logic. Each module should be independently testable and have minimal knowledge of other modules.

**What belongs in modules:**

- Domain models (e.g., `Event`, `Reservation`, `Band`)
- Actions that operate on domain models
- Domain events (e.g., `ReservationCreated`)
- Interfaces that define contracts for external dependencies
- Filament resources for the domain
- Module-specific views and components
- Domain enums and value objects

**What modules should NOT contain:**

- Authorization policies (these go in `app/Policies/`)
- Cross-module coordination logic
- Direct references to other module's concrete classes (use interfaces)
- Global side effects (cache invalidation, notifications to other domains)

### Integration Layer (`app/`)

The integration layer **coordinates between modules** and handles cross-cutting concerns. It's the "glue" that connects independent modules.

**What belongs in the integration layer:**

- **Policies** - Authorization logic (see [authorization.md](authorization.md))
- **Listeners** - React to domain events from modules
- **Observers** - Model lifecycle hooks for cross-cutting concerns
- **Integration Models** - Models that bridge two modules
- **Interface Bindings** - Wire up module interfaces to implementations

## Module Structure

Each module follows this structure:

```tree
app-modules/events/
├── src/
│   ├── Actions/              # Business logic
│   ├── Models/               # Domain models
│   ├── Enums/                # Domain enums
│   ├── Events/               # Domain events (Laravel events)
│   ├── Data/                 # DTOs (spatie/laravel-data)
│   ├── Contracts/            # Interfaces for external dependencies
│   ├── Concerns/             # Traits for models
│   ├── Notifications/        # Domain notifications
│   └── Providers/            # Module service provider
├── resources/
│   └── views/                # Module views
├── database/
│   ├── migrations/
│   └── factories/
└── tests/
    └── Feature/
```

## Cross-Module Communication

Modules should communicate through **loose coupling** mechanisms:

### 1. Domain Events

Modules dispatch events; the integration layer listens and coordinates.

```php
// In module: dispatch event
event(new ReservationCreated($reservation));

// In app/Listeners: handle cross-module logic
class SyncReservationToCalendar
{
    public function handle(ReservationCreated $event): void
    {
        // Coordinate with calendar module
    }
}
```

### 2. Interfaces

Modules define interfaces for dependencies; implementations are bound in the integration layer.

```php
// In Events module: define what it needs
interface ConflictCheckerInterface
{
    public function hasConflicts(Period $period): bool;
}

// In SpaceManagement module: implement the interface
class ReservationConflictChecker implements ConflictCheckerInterface
{
    public function hasConflicts(Period $period): bool { ... }
}

// In AppServiceProvider: bind them together
$this->app->bind(ConflictCheckerInterface::class, ReservationConflictChecker::class);
```

### 3. Integration Models

When two modules need to share a relationship, create a model in the integration layer.

```php
// app/Models/EventReservation.php
// Bridges Events module and SpaceManagement module
class EventReservation extends Reservation
{
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
```

## Current Modules

| Module | Domain | Key Models |
| ------ | ------ | ---------- |
| **Events** | Event/show management | `Event`, `Venue` |
| **SpaceManagement** | Practice space reservations | `Reservation`, `RehearsalReservation` |
| **Finance** | Pricing, credits, payments | `Charge`, `Credit` |
| **Bands** | Band profiles | `Band`, `BandMember` |
| **Equipment** | Equipment loans | `Equipment`, `EquipmentLoan` |
| **Moderation** | Content moderation | `Report`, `Revision` |
| **MemberProfiles** | Member directory | `MemberProfile` |
| **Support** | Shared utilities | Traits, base classes |

## Module Dependencies

Modules can depend on other modules, but prefer depending on interfaces rather than concrete classes.

```text
Events → SpaceManagement (via ConflictCheckerInterface)
SpaceManagement → Finance (via Chargeable interface)
Equipment → Support (via HasTimePeriod trait)
All modules → Support (shared traits)
```

## Adding a New Module

1. Create the module structure:

   ```bash
   php artisan make:module ModuleName
   ```

2. Add models, actions, and business logic to the module

3. Define interfaces for any external dependencies the module needs

4. Create policies in `app/Policies/` (not in the module)

5. Add listeners in `app/Listeners/` for cross-module coordination

6. Bind interfaces in `AppServiceProvider`

## Testing

- **Module tests** (`app-modules/*/tests/`) test the module in isolation
- **Integration tests** (`tests/Feature/`) test cross-module workflows
- **Policy tests** (`tests/Feature/Policies/`) test authorization

## Common Patterns

### Model Helpers for Policies

Models expose simple boolean helpers that policies use:

```php
// In module model
public function isOrganizedBy(User $user): bool
{
    return $this->organizer_id === $user->id;
}

// In integration layer policy
public function update(User $user, Event $event): bool
{
    return $this->manage($user) || $event->isOrganizedBy($user);
}
```

### Shared Traits in Support Module

Common behavior goes in the Support module:

```php
// app-modules/support/src/Concerns/HasTimePeriod.php
trait HasTimePeriod
{
    public function getPeriod(): Period { ... }
    public function getDuration(): float { ... }
}

// Used by models in other modules
class Reservation extends Model
{
    use HasTimePeriod;
}
```

### Domain Events for Side Effects

Keep side effects out of core business logic:

```php
// In action: just do the domain work and dispatch event
class CreateReservation
{
    public function handle(array $data): Reservation
    {
        $reservation = Reservation::create($data);
        event(new ReservationCreated($reservation));
        return $reservation;
    }
}

// In listener: handle side effects
class InvalidateCacheOnReservation
{
    public function handle(ReservationCreated $event): void
    {
        Cache::tags(['reservations'])->flush();
    }
}
```
