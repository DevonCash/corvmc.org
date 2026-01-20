# Module Refactor

## Executive Summary

Transform the current action-based monolithic architecture into a **Monolithic Modular Architecture** using **internachi/modular** with clear domain boundaries, explicit interfaces, and managed cross-cutting concerns. This maintains single-deployment simplicity while improving organization, testability, and maintainability.

**Package**: [internachi/modular](https://github.com/InterNACHI/modular) - Laravel module system using Composer path repositories
**Scope**: ~30k LOC organized into 7 domain modules + shared kernel
**Timeline**: 8-10 weeks (6 phases) - Faster with built-in tooling
**Approach**: Incremental migration using internachi/modular commands

---

## Proposed Module Structure

Using **internachi/modular** conventions, modules are Laravel packages in `app-modules/` with automatic Composer path repository registration.

### Core Modules (Domain-Specific)

```
app-modules/
├── space-management/          # Reservations + Recurring (43 actions)
│   ├── composer.json
│   ├── src/                   # Models, Actions, Services
│   ├── routes/                # Module routes
│   ├── resources/             # Views, lang, Filament components
│   ├── database/migrations/
│   └── tests/
│
├── events/                    # Events & Productions (16 actions)
├── membership/                # Users + MemberProfiles + Bands (23 actions)
├── finance/                   # Payments + Subscriptions + Credits (26 actions)
├── equipment/                 # Equipment Library (6 actions)
└── sponsorship/               # Sponsorship system (3 actions)
```

### Shared Module (Cross-Cutting)

```
app-modules/
└── shared/                    # Cross-cutting concerns
    ├── composer.json
    ├── src/
    │   ├── ContentModeration/ # Trust + Reports + Revisions
    │   ├── Notifications/     # 33 notification classes
    │   ├── Integrations/      # GoogleCalendar
    │   └── Support/           # Shared concerns, DTOs, casts
    └── tests/
```

### Platform Layer

```
app/
├── Filament/            # Panel-specific resources (Member/Band/Staff)
├── Http/                # Public controllers
└── Providers/           # App service providers
```

**Note**: Each module is a self-contained Laravel package with its own namespace (e.g., `CorvMC\SpaceManagement`), registered via Composer path repositories.

---

## Module Details

### SpaceManagement Module

**Owns**: Practice space booking, conflict detection, time slots

**Models**:
- `Reservation` (base class, STI)
- `RehearsalReservation`
- `EventReservation`

**Key Actions**:
- Conflict detection (30 actions)
- Recurring reservations (13 actions)
- Cost calculation
- Availability checking

**Note**: `RecurringReservation` is deprecated. `RecurringSeries` moved to Shared module as it's used across domains.

**Dependencies**:
- **Finance**: `CreditManagerInterface` for free hours

**Provides**:
- `ConflictDetectorInterface` - Used by Events when creating EventReservations
- `PricingCalculatorInterface`
- `ReservationManagerInterface` - Used by Events to create/manage EventReservations

### Events Module

**Owns**: Event publishing, performer lineup, event lifecycle

**Models**:
- `Event`
- `Venue`

**Key Actions**:
- Event CRUD (16 actions)
- Publishing workflow
- Performer management
- Rescheduling

**Dependencies**:
- **SpaceManagement**: `ReservationManagerInterface` to create/manage EventReservations
- **Membership**: Band performers
- **Shared**: Content moderation

**Note**: Events don't do conflict checking directly - they attempt to create EventReservations through SpaceManagement, which handles all conflict detection internally.

### Membership Module

**Owns**: User authentication, profiles, band management

**Models**:
- `User`
- `MemberProfile`
- `Band`
- `BandMember`
- `StaffProfile`

**Key Actions**:
- User management (5 actions)
- Member profiles (9 actions)
- Band operations (9 actions)
- Invitation system

**Dependencies**:
- **Shared**: Content moderation, invitations

**Provides**:
- `MemberRepositoryInterface`

### Finance Module

**Owns**: Payment processing, subscriptions, credit ledger

**Models**:
- `Subscription`
- `UserCredit`
- `CreditTransaction`
- `CreditAllocation`
- `PromoCode`

**Key Actions**:
- Payment calculations (10 actions)
- Subscription management (9 actions)
- Credit system (4 actions)
- Member benefits (3 actions)

**Provides**:
- `PaymentProcessorInterface`
- `CreditManagerInterface`

**Dependencies**:
- Stripe (via Laravel Cashier)

### Equipment Module

**Owns**: Equipment tracking and loans

**Models**:
- `Equipment`
- `EquipmentLoan`
- `EquipmentDamageReport`

**Key Actions**: 6 actions for checkout/return

**Dependencies**: Minimal (most isolated module)

### Sponsorship Module

**Owns**: Corporate/organizational sponsorships

**Models**: `Sponsor`

**Key Actions**: 3 actions for sponsor management

### Shared Module

**Owns**: Cross-cutting concerns used across multiple domains

**ContentModeration Sub-domain**:
- **Models**: `ContentModel` (abstract base), `Report`, `Revision`, `TrustTransaction`, `UserTrustBalance`
- **Traits**: `Reportable`, `Revisionable`, `HasTrust`
- **Used By**: All content types (Event, Band, MemberProfile)
- **Why Shared**: Genuinely cross-cutting concern with identical logic

**Support Sub-domain**:
- **Models**: `RecurringSeries` (used by Events and Reservations)
- **Traits**: `HasTimePeriod`, `HasVisibility`, `HasPublishing`, `HasPaymentStatus`
- **Casts**: `MoneyCast`
- **DTOs**: `LocationData`, etc.
- **Why Shared**: Reusable across multiple modules

**Integrations Sub-domain**:
- **GoogleCalendar**: Syncs reservations and events
- **Why Shared**: Used by both SpaceManagement and Events modules

**Notifications Sub-domain**:
- Organized by domain (Reservations, Events, Membership, etc.)
- **Why Shared**: Consistent notification patterns across domains

---

## Solutions for Tight Coupling

### Problem 1: Membership → Credits → Reservations Cycle

**Current**: Direct dependencies create circular knowledge

**Solution**: Event-Driven Decoupling + Interface Contracts

```php
// Finance module provides interface
interface CreditManagerInterface {
    public function getAvailableCredits(User $user): int;
    public function deductCredits(User $user, int $blocks, string $reason): void;
    public function refundCredits(User $user, int $blocks, string $reason): void;
}

// SpaceManagement depends on interface (not implementation)
class PricingCalculator {
    public function __construct(private CreditManagerInterface $creditManager) {}

    public function calculateCost(Reservation $reservation): Money {
        $credits = $this->creditManager->getAvailableCredits($reservation->user);
        // Apply credits to cost...
    }
}

// Event-driven credit allocation
Event::listen(
    MembershipStatusChanged::class,
    AllocateCreditsOnMembershipChange::class
);
```

**Benefits**:
- No direct module coupling
- Async processing possible
- Testable with mock interfaces

### Problem 2: Event → SpaceManagement Dependency (Practice Space)

**Current**: Events need to reserve practice space, creating potential for conflicts

**Solution**: One-Way Dependency through Interface

Events don't do conflict checking directly. Instead, they attempt to create EventReservations through SpaceManagement's API:

```php
// app-modules/space-management/src/Contracts/ReservationManagerInterface.php
interface ReservationManagerInterface {
    public function createEventReservation(
        Event $event,
        Carbon $start,
        Carbon $end
    ): EventReservation;

    public function updateEventReservation(
        EventReservation $reservation,
        Carbon $start,
        Carbon $end
    ): EventReservation;

    public function cancelEventReservation(EventReservation $reservation): void;
}

// app-modules/space-management/src/Services/ReservationManager.php
class ReservationManager implements ReservationManagerInterface {
    public function __construct(
        private ConflictDetector $conflictDetector
    ) {}

    public function createEventReservation(...): EventReservation {
        // Check conflicts internally (SpaceManagement responsibility)
        $conflicts = $this->conflictDetector->findConflicts($start, $end);

        if ($conflicts->isNotEmpty()) {
            throw new ReservationConflictException($conflicts);
        }

        return EventReservation::create([...]);
    }
}

// app-modules/events/src/Actions/Events/SyncEventSpaceReservation.php
class SyncEventSpaceReservation extends Action {
    public function __construct(
        private ReservationManagerInterface $reservationManager
    ) {}

    public function handle(Event $event): void {
        if (!$event->usesPracticeSpace()) {
            return;
        }

        try {
            if ($event->spaceReservation) {
                $this->reservationManager->updateEventReservation(
                    $event->spaceReservation,
                    $event->start_datetime,
                    $event->end_datetime
                );
            } else {
                $reservation = $this->reservationManager->createEventReservation(
                    $event,
                    $event->start_datetime,
                    $event->end_datetime
                );
                $event->spaceReservation()->save($reservation);
            }
        } catch (ReservationConflictException $e) {
            // Handle conflict (notify user, prevent save, etc.)
            throw $e;
        }
    }
}
```

**Benefits**:
- Clear ownership: SpaceManagement owns all conflict detection
- One-way dependency: Events → SpaceManagement (not bidirectional)
- ConflictDetector stays internal to SpaceManagement
- Events just uses public API to manage EventReservations

### Problem 3: ContentModel Inheritance (Trust/Reports/Revisions)

**Current**: All content types extend ContentModel

**Solution**: Keep in Shared Kernel (Appropriate Cross-Cutting)

This is genuinely shared behavior that applies identical logic across all content types. The shared kernel explicitly owns this.

---

## Module Communication Patterns

### 1. Interface Contracts (Synchronous)

Use when modules need data/services from each other:

```php
// Finance provides, SpaceManagement consumes
interface CreditManagerInterface {
    public function getAvailableCredits(User $user): int;
}

// Registered in FinanceServiceProvider
$this->app->bind(CreditManagerInterface::class, CreditManager::class);

// Used in SpaceManagement
public function __construct(private CreditManagerInterface $credits) {}
```

### 2. Domain Events (Asynchronous)

Use for side effects and cross-module reactions:

```php
// SpaceManagement fires event
event(new ReservationCreated($reservation));

// Finance listens
Event::listen(
    ReservationCreated::class,
    DeductCreditsOnReservation::class
);

// GoogleCalendar listens
Event::listen(
    ReservationCreated::class,
    SyncToGoogleCalendar::class
);
```

### 3. Shared Services (Common Utilities)

Use for genuinely shared logic:

- `ContentModerationService` (trust/reports/revisions)
- Shared traits, casts, DTOs
- GoogleCalendar integration

---

## Filament Resource Organization

### Hybrid Approach: Module Components + Panel Customization

**Module-Level Components** (reusable):
```
modules/SpaceManagement/Filament/
├── Schemas/ReservationForm.php        # Reusable form schema
├── Tables/ReservationTable.php        # Reusable table columns
└── Actions/CreateReservationAction.php # Reusable actions
```

**Panel-Level Resources** (customized):
```
app/Filament/Member/Resources/MyReservationsResource.php
  → Uses ReservationForm from module
  → Filters to current user's reservations
  → Member-specific actions

app/Filament/Band/Resources/BandReservationsResource.php
  → Uses ReservationForm from module
  → Filters to current band's reservations
  → Band-specific actions
```

**Benefits**:
- DRY: Shared schemas/tables
- Flexibility: Panel-specific customization
- Clear ownership: Business logic in modules, UI in panels

---

## Migration Strategy

Using **internachi/modular**, the migration is faster and more automated thanks to built-in commands.

### Phase 1: Install & Setup (Week 1)

**Goal**: Install internachi/modular and create module scaffolds

**Tasks**:
1. Install package:
   ```bash
   composer require internachi/modular
   ```

2. Publish and customize config (recommended):
   ```bash
   php artisan vendor:publish --tag=modular-config
   ```
   - Change default namespace from "Modules" to "CorvMC" in `config/app-modules.php`
   - This makes modules easier to extract into separate packages later

3. Sync configuration:
   ```bash
   php artisan modules:sync
   ```
   - Updates `phpunit.xml` with Modules test suite
   - Configures PhpStorm Laravel plugin for module views

4. Create module scaffolds (least to most coupled):
   ```bash
   php artisan make:module equipment
   php artisan make:module sponsorship
   php artisan make:module space-management
   php artisan make:module events
   php artisan make:module membership
   php artisan make:module finance
   php artisan make:module shared
   ```
   - Each command creates directory structure and registers in `composer.json`
   - Run `composer update` after creating all modules

**Verification**:
- Application runs without errors
- `composer.json` contains path repositories for all modules
- Run `php artisan modules:list` to see all modules

### Phase 2: Migrate Equipment Module (Week 2)

**Goal**: Complete one full module migration to validate the pattern

**Tasks**:
1. Move models:
   ```bash
   # Equipment module uses CorvMC\Equipment namespace
   mv app/Models/Equipment.php → app-modules/equipment/src/Models/
   mv app/Models/EquipmentLoan.php → app-modules/equipment/src/Models/
   mv app/Models/EquipmentDamageReport.php → app-modules/equipment/src/Models/
   ```
   - Update namespaces to `CorvMC\Equipment\Models`
   - Update `composer.json` PSR-4 autoloading in module

2. Move actions:
   ```bash
   mv app/Actions/Equipment/ → app-modules/equipment/src/Actions/
   ```
   - Update namespaces to `CorvMC\Equipment\Actions`

3. Move migrations:
   ```bash
   mv database/migrations/*equipment* → app-modules/equipment/database/migrations/
   ```

4. Create class aliases for backward compatibility:
   ```php
   // app/Models/Equipment.php (temporary)
   class_alias(
       \CorvMC\Equipment\Models\Equipment::class,
       \App\Models\Equipment::class
   );
   ```

5. Find/replace imports across codebase (IDE assistance)

6. Create module service provider if needed for custom bindings

**Verification**:
- All tests pass
- Equipment functionality works (view, checkout, return)
- Migrations run successfully

### Phase 3: Migrate Remaining Domain Modules (Week 3-5)

**Goal**: Migrate all domain modules following Equipment pattern

**Order**: Sponsorship → SpaceManagement → Events → Membership → Finance

**Process per module**:
1. Move models to `app-modules/{module}/src/Models/`
2. Move actions to `app-modules/{module}/src/Actions/`
3. Move migrations to `app-modules/{module}/database/migrations/`
4. Move Filament resources to `app-modules/{module}/src/Filament/`
5. Update all namespaces to `CorvMC\{Module}\`
6. Create class aliases in `app/Models/` for backward compatibility
7. Find/replace imports
8. Test module functionality

**Using internachi/modular generators**:
```bash
# Generate new classes directly in modules
php artisan make:model Equipment --module=equipment
php artisan make:controller EquipmentController --module=equipment
php artisan make:migration create_equipment_table --module=equipment
php artisan make:test EquipmentTest --module=equipment
```

**SpaceManagement specifics**:
- Move all Reservation STI classes together (Reservation, RehearsalReservation, EventReservation)
- Note: `RecurringReservation` is deprecated, do not migrate
- Move conflict detection logic (stays in SpaceManagement - it's their domain)
- `RecurringSeries` will be moved to Shared module in Phase 4

**Events specifics**:
- Move Event and Venue models
- Keep space conflict integration working

**Membership specifics**:
- Move User, MemberProfile, Band, BandMember
- Keep auth working (User model must be discoverable)

**Finance specifics**:
- Move subscription and credit models
- Keep Cashier integration working

**Verification after each module**:
- Run `composer dump-autoload`
- All tests pass
- Module-specific features work

### Phase 4: Create Shared Module (Week 6)

**Goal**: Consolidate cross-cutting concerns into shared module

**Tasks**:
1. Move to `app-modules/shared/src/ContentModeration/`:
   - `app/Models/ContentModel.php`
   - `app/Concerns/Reportable.php`, `Revisionable.php`
   - `app/Models/Report.php`, `Revision.php`, trust models
   - `app/Actions/Trust/`, `Reports/`, `Revisions/`

2. Move to `app-modules/shared/src/Support/`:
   - **Models**: `app/Models/RecurringSeries.php` (used by Events and Reservations)
   - **Concerns**: `app/Concerns/HasTimePeriod.php`, `HasPaymentStatus.php`, `HasVisibility.php`, `HasPublishing.php`
   - **Casts**: `app/Casts/MoneyCast.php`
   - **DTOs**: `app/Data/LocationData.php`

3. Move to `app-modules/shared/src/Notifications/`:
   - Organize by domain (Reservations/, Events/, Membership/)

4. Move to `app-modules/shared/src/Integrations/`:
   - `GoogleCalendar/` service (used by both SpaceManagement and Events)

5. Update `app-modules/shared/composer.json` to provide shared dependencies

**Namespace**: `CorvMC\Shared\`

**Verification**: Cross-cutting features work (reports, revisions, trust, recurring series)

### Phase 5: Decouple & Refactor (Week 7-8)

**Goal**: Implement interface contracts and event-driven architecture

**Tasks**:
1. Create interface contracts:
   ```bash
   php artisan make:interface CreditManagerInterface --module=finance
   php artisan make:interface PaymentProcessorInterface --module=finance
   php artisan make:interface ReservationManagerInterface --module=space-management
   ```

2. Implement in module service providers:
   ```php
   // app-modules/finance/src/FinanceServiceProvider.php
   public function register(): void
   {
       $this->app->bind(
           CreditManagerInterface::class,
           CreditManager::class
       );
   }

   // app-modules/space-management/src/SpaceManagementServiceProvider.php
   public function register(): void
   {
       $this->app->bind(
           ReservationManagerInterface::class,
           ReservationManager::class
       );
   }
   ```

3. Add domain events:
   ```bash
   php artisan make:event MembershipStatusChanged --module=membership
   php artisan make:event ReservationCreated --module=space-management
   php artisan make:event EventPublished --module=events
   ```

4. Add event listeners:
   ```bash
   php artisan make:listener AllocateCreditsOnMembershipChange --module=finance
   php artisan make:listener DeductCreditsOnReservation --module=finance
   ```

5. Extract Filament components:
   - Move shared schemas/tables to `app-modules/{module}/src/Filament/`
   - Refactor panel resources to use module components

6. Replace direct dependencies with interfaces

**Verification**:
- Reservation flow works (pricing, credits, payment)
- Credit allocation works on subscription
- Conflict detection works
- All Filament panels work

### Phase 6: Remove Backward Compatibility (Week 9-10)

**Goal**: Clean up aliases, finalize boundaries, optimize

**Tasks**:
1. Remove class aliases from `app/Models/`
2. Update any remaining hardcoded namespace references
3. Run full test suite
4. Cache modules for production:
   ```bash
   php artisan modules:cache
   ```
5. Update `CLAUDE.md` with module architecture:
   - Document module structure
   - Explain how to work with modules
   - List module-specific commands
6. Add PHPStan rules to prevent cross-module violations
7. Configure CI/CD to test modules individually

**Verification**:
- All tests pass without aliases
- No direct cross-module model imports (use interfaces)
- PHPStan passes at current level
- `php artisan modules:list` shows all active modules
- Production build works with cached modules

---

## Trade-offs Analysis

### Benefits

✅ **Improved Organization**
- Clear domain boundaries (easier navigation)
- Smaller contexts (reduced cognitive load)
- New developers understand modules independently
- **internachi/modular**: Auto-discovery for commands, migrations, policies, factories

✅ **Better Testability**
- Test modules in isolation
- Mock interfaces for dependencies
- Faster focused test suites
- **internachi/modular**: Auto-configures `phpunit.xml` with module test suite

✅ **Reduced Coupling**
- Explicit interfaces prevent hidden dependencies
- Event-driven allows async processing
- Clear shared kernel boundaries
- **internachi/modular**: Composer path repositories enforce package isolation

✅ **Scalability**
- Modules evolve independently
- Team members can own modules
- Can extract to services later if needed
- **internachi/modular**: Each module is already a Laravel package (easy extraction)

✅ **Type Safety**
- Interface contracts improve PHPStan
- Explicit dependencies easier to type-hint
- Better IDE autocomplete
- **internachi/modular**: Clear namespacing improves static analysis

✅ **Developer Experience**
- **internachi/modular**: Built-in commands (`make:module`, `modules:list`, `modules:cache`)
- **internachi/modular**: `--module=` flag on all Laravel generators
- **internachi/modular**: Blade component namespacing (`<x-equipment::card />`)
- **internachi/modular**: Translation namespacing (`__('equipment::messages.welcome')`)

### Costs

❌ **Migration Effort**
- 8-10 weeks focused work (reduced from 12-14 with custom solution)
- Risk of breaking changes
- Requires comprehensive test coverage
- **Mitigated**: Built-in commands reduce manual work

❌ **Added Complexity**
- More directories to navigate
- Service provider boilerplate (if needed)
- Learning curve for events/interfaces
- **Mitigated**: Follows standard Laravel package conventions

❌ **Performance** (negligible)
- Module discovery overhead (minimal with caching)
- Event dispatching latency (sub-ms)
- More autoload paths (cached by opcache)
- **Optimization**: `php artisan modules:cache` for production

❌ **Maintenance**
- Keep boundaries clear
- Prevent module drift
- Resist creating "god modules"
- **Mitigated**: Package structure provides natural boundaries

### Risk Mitigation

**Backward Compatibility**: Class aliases during migration
**Testing**: Maintain 80%+ coverage throughout
**Rollback**: Git branches per phase, can pause in hybrid state
**Incremental**: One module at a time, validate before next

---

## Directory Structure (Final State)

Using **internachi/modular** conventions:

```
corvmc-redux/
├── app/
│   ├── Filament/
│   │   ├── Member/              # Member panel resources
│   │   ├── Band/                # Band tenant panel
│   │   └── Providers/
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Middleware/
│   └── Providers/
│       └── AppServiceProvider.php
│
├── app-modules/                 # Module directory (internachi/modular)
│   ├── space-management/
│   │   ├── composer.json        # Module package definition
│   │   ├── src/
│   │   │   ├── Models/          # Reservation, RehearsalReservation, EventReservation
│   │   │   ├── Actions/         # 43 actions
│   │   │   ├── Contracts/       # ReservationManagerInterface
│   │   │   ├── Services/        # ReservationManager, ConflictDetector, PricingCalculator
│   │   │   ├── Events/          # ReservationCreated
│   │   │   ├── Listeners/
│   │   │   ├── Filament/        # Shared Filament components
│   │   │   └── SpaceManagementServiceProvider.php
│   │   ├── routes/              # Module-specific routes
│   │   ├── resources/
│   │   │   ├── views/
│   │   │   └── lang/
│   │   ├── database/migrations/
│   │   ├── tests/
│   │   └── config/
│   │
│   ├── events/
│   │   ├── composer.json
│   │   ├── src/
│   │   │   ├── Models/          # Event, Venue
│   │   │   ├── Actions/         # 16 actions
│   │   │   └── EventsServiceProvider.php
│   │   └── ...
│   │
│   ├── membership/
│   │   ├── composer.json
│   │   ├── src/
│   │   │   ├── Models/          # User, MemberProfile, Band
│   │   │   ├── Actions/         # 23 actions
│   │   │   └── MembershipServiceProvider.php
│   │   └── ...
│   │
│   ├── finance/
│   │   ├── composer.json
│   │   ├── src/
│   │   │   ├── Models/          # Subscription, Credits
│   │   │   ├── Actions/         # 26 actions
│   │   │   ├── Contracts/       # CreditManagerInterface
│   │   │   └── FinanceServiceProvider.php
│   │   └── ...
│   │
│   ├── equipment/
│   │   ├── composer.json
│   │   ├── src/
│   │   │   ├── Models/          # Equipment, EquipmentLoan
│   │   │   └── Actions/         # 6 actions
│   │   └── ...
│   │
│   ├── sponsorship/
│   │   ├── composer.json
│   │   ├── src/
│   │   │   ├── Models/          # Sponsor
│   │   │   └── Actions/         # 3 actions
│   │   └── ...
│   │
│   └── shared/                  # Cross-cutting concerns module
│       ├── composer.json
│       ├── src/
│       │   ├── ContentModeration/
│       │   │   ├── Models/      # ContentModel, Report, Revision
│       │   │   ├── Actions/     # Trust, Reports, Revisions
│       │   │   └── Concerns/    # Reportable, Revisionable traits
│       │   ├── Support/
│       │   │   ├── Models/      # RecurringSeries (used across domains)
│       │   │   ├── Data/        # DTOs (LocationData, etc.)
│       │   │   ├── Casts/       # MoneyCast
│       │   │   └── Concerns/    # HasTimePeriod, HasVisibility, etc.
│       │   ├── Notifications/   # By domain (Reservations, Events, etc.)
│       │   ├── Integrations/
│       │   │   └── GoogleCalendar/
│       │   └── SharedServiceProvider.php
│       └── tests/
│
├── tests/
│   ├── Feature/
│   └── Unit/
│
├── composer.json                # Path repositories for all modules
├── phpunit.xml                  # Auto-updated by modules:sync
└── CLAUDE.md                    # Updated with module info
```

**Key Features**:
- Each module in `app-modules/` is a Laravel package with `composer.json`
- Modules auto-register via Composer path repositories
- Auto-discovery for migrations, commands, policies, factories
- Blade components: `<x-equipment::component />`
- Translations: `__('equipment::messages.key')`

---

## Critical Files to Modify

### Phase 1: Install & Setup
- `/Users/dcash/Projects/_sites/corvmc-redux/composer.json` - Auto-updated by `make:module` commands with path repositories
- `/Users/dcash/Projects/_sites/corvmc-redux/config/app-modules.php` - CREATE: Configure namespace to "CorvMC"
- `/Users/dcash/Projects/_sites/corvmc-redux/phpunit.xml` - Auto-updated by `modules:sync`

### Phase 2: Equipment Module (Example)
- `/Users/dcash/Projects/_sites/corvmc-redux/app/Models/Equipment.php` - Move to `app-modules/equipment/src/Models/`
- `/Users/dcash/Projects/_sites/corvmc-redux/app/Actions/Equipment/` - Move to `app-modules/equipment/src/Actions/`
- `/Users/dcash/Projects/_sites/corvmc-redux/database/migrations/*equipment*` - Move to `app-modules/equipment/database/migrations/`

### Phase 3-4: All Modules
- Move models from `app/Models/` to `app-modules/{module}/src/Models/`
- Move actions from `app/Actions/` to `app-modules/{module}/src/Actions/`
- Move migrations to module `database/migrations/` directories
- Update all namespaces to `CorvMC\{Module}\`

### Phase 5: Decoupling
- Create interface contracts in module `src/Contracts/` directories
- Implement service providers in each module
- Add domain events and listeners

### Phase 6: Final
- `/Users/dcash/Projects/_sites/corvmc-redux/CLAUDE.md` - Document module architecture
- Remove class aliases from `app/Models/`

---

## Success Criteria

✅ All modules have explicit service providers
✅ No direct cross-module model imports (use interfaces)
✅ Shared kernel limited to ContentModeration, Support, Notifications
✅ 80%+ test coverage maintained throughout migration
✅ All Filament panels work without disruption
✅ No performance regression (<5% response time increase acceptable)
✅ PHPStan level 2 maintained or improved

---

## Verification Plan

After each phase:

1. **Run full test suite**: `composer test`
2. **Check manual flows**:
   - Create reservation (with credits)
   - Create event (check conflicts)
   - Create band + invite member
   - Process subscription payment
3. **Test Filament panels**:
   - Member panel navigation
   - Band tenant switching
   - Admin functions
4. **Performance check**: Response time for homepage, events, reservations
5. **PHPStan**: `vendor/bin/phpstan analyse`

---

## Alternative Approaches Considered

### ✅ internachi/modular Package (RECOMMENDED)
**Why this is the best choice:**
- ✅ Follows Laravel conventions (not opinionated like nWidart)
- ✅ Lightweight - uses Composer path repositories
- ✅ Built-in tooling (`make:module`, `modules:list`, `--module=` flags)
- ✅ Auto-discovery for migrations, commands, policies, factories
- ✅ Each module is already a Laravel package (easy extraction)
- ✅ Well-maintained, active community
- ✅ Works perfectly with action-based architecture
- ✅ Compatible with Filament (follows standard package patterns)
- ✅ Reduces migration effort with automation (8-10 weeks vs 12-14)
- ✅ No need for custom service provider registration
- ✅ Production optimization with `modules:cache`

### ❌ nWidart/laravel-modules Package
- Opinionated MVC structure doesn't match action-based architecture
- Overkill for monolithic deployment (designed for dynamic module loading)
- Harder to customize for Filament
- More boilerplate and configuration
- **Verdict**: Too heavy for our needs

### ❌ Custom Monolithic Modular Solution
- Would require manual service provider registration
- No built-in tooling (need to create own generators)
- More maintenance overhead
- Reinventing the wheel when internachi/modular exists
- **Verdict**: Unnecessary when better solution exists

### ❌ Keep Monolith, Add Namespaces Only
- Doesn't solve tight coupling
- No boundary enforcement
- Limited testability improvements
- No module-level testing support
- **Verdict**: Insufficient for long-term maintainability

### ❌ Microservices
- Massive complexity increase (distributed systems challenges)
- Not justified by current scale (~30k LOC)
- Would destroy Filament integration (requires single app)
- Network latency, transaction boundaries, debugging complexity
- **Verdict**: Premature optimization

---

## Recommendation

**Proceed with internachi/modular** for modular architecture using 6-phase incremental migration over 8-10 weeks. This provides:

✅ Organization and testability benefits of modules
✅ Simplicity of monolithic deployment
✅ Built-in Laravel tooling and conventions
✅ Easy extraction to packages later if needed
✅ Reduced migration effort with automation

**Start with Equipment module** (most isolated) to validate the pattern before tackling more complex modules.

**Sources:**
- [InterNACHI/modular GitHub](https://github.com/InterNACHI/modular)
- [Laravel News: Modularize your Laravel apps](https://laravel-news.com/package/internachi-modular)
