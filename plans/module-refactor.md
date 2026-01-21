# Module Refactor

## Executive Summary

Transform the current action-based monolithic architecture into a **Monolithic Modular Architecture** using **internachi/modular** with clear domain boundaries, explicit interfaces, and managed cross-cutting concerns. This maintains single-deployment simplicity while improving organization, testability, and maintainability.

**Package**: [internachi/modular](https://github.com/InterNACHI/modular) - Laravel module system using Composer path repositories
**Scope**: ~30k LOC organized into 8 domain modules + support library
**Approach**: Incremental migration using internachi/modular commands

---

## Proposed Module Structure

Using **internachi/modular** conventions, modules are Laravel packages in `app-modules/` with automatic Composer path repository registration.

### Core Modules (Domain-Specific)

```
app-modules/
├── space-management/          # Reservations + Recurring (43 actions)
├── events/                    # Events & Productions (16 actions)
├── membership/                # Users + Profiles + Bands (23 actions)
├── finance/                   # Payments + Subscriptions + Credits (26 actions)
├── equipment/                 # Equipment Library (6 actions) ✅ DONE
├── sponsorship/               # Sponsorship system (3 actions) ✅ DONE
└── moderation/                # Trust + Reports + Revisions ✅ DONE
```

### Support Module (Library/Infrastructure)

```
app-modules/
└── support/                   # Library components used across domains
    ├── composer.json
    ├── src/
    │   ├── Models/            # RecurringSeries
    │   ├── Concerns/          # HasTimePeriod, HasRecurringSeries
    │   ├── Casts/             # MoneyCast
    │   └── Enums/             # RecurringSeriesStatus
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
- Conflict detection (~30 actions)
- Recurring reservations (~13 actions)
- Cost calculation
- Availability checking

**Concerns** (domain-specific):
- `HasPaymentStatus` - only used by Reservation

**Notifications** (domain-specific):
- `ReservationCreatedNotification`
- `ReservationConfirmedNotification`
- `ReservationCancelledNotification`
- `ReservationReminderNotification`
- `ReservationConfirmationReminderNotification`
- `ReservationAutoCancelledNotification`
- `ReservationCreatedTodayNotification`
- `DailyReservationDigestNotification`

**Note**: `RecurringReservation` is deprecated - do not migrate.

**Dependencies**:
- **Finance**: `CreditManagerInterface` for free hours
- **Support**: `RecurringSeries`, `MoneyCast`, `HasTimePeriod`

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
- Event CRUD (~16 actions)
- Publishing workflow
- Performer management
- Rescheduling

**Concerns** (domain-specific):
- `HasPublishing` - only used by Event
- `HasPoster` - only used by Event

**DTOs** (domain-specific):
- `LocationData`
- `VenueLocationData`

**Notifications** (domain-specific):
- `EventCreatedNotification`
- `EventUpdatedNotification`
- `EventCancelledNotification`
- `EventPublishedNotification`

**Dependencies**:
- **SpaceManagement**: `ReservationManagerInterface` to create/manage EventReservations
- **Membership**: Band performers
- **Moderation**: Content moderation (reports, revisions)
- **Support**: `RecurringSeries`, `HasTimePeriod`

**Note**: Events don't do conflict checking directly - they attempt to create EventReservations through SpaceManagement, which handles all conflict detection internally.

### Membership Module

**Owns**: User authentication, profiles, band management

**Models**:
- `User`
- `MemberProfile`
- `Band`
- `BandMember`
- `StaffProfile`
- `Invitation`

**Key Actions**:
- User management (~5 actions)
- Member profiles (~9 actions)
- Band operations (~9 actions)
- Invitation system (~10 actions)
- Staff profiles (~11 actions)

**Concerns** (domain-specific):
- `HasMembershipStatus` - only used by User

**DTOs** (domain-specific):
- `UserSettingsData`
- `ContactData`

**Notifications** (domain-specific):
- `NewMemberWelcomeNotification`
- `UserCreatedNotification`
- `UserUpdatedNotification`
- `UserDeactivatedNotification`
- `UserInvitationNotification`
- `EmailVerificationNotification`
- `PasswordResetNotification`
- `BandInvitationNotification`
- `BandInvitationAcceptedNotification`
- `BandOwnershipInvitationNotification`
- `ContactFormSubmissionNotification`

**Dependencies**:
- **Moderation**: Content moderation for bands/profiles

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
- `PromoCodeRedemption`

**Key Actions**:
- Payment calculations (~10 actions)
- Subscription management (~9 actions)
- Credit system (~4 actions)
- Member benefits (~3 actions)

**Concerns** (domain-specific):
- `HasCredits` - only used by User (for credits)

**Notifications** (domain-specific):
- `MembershipExpiredNotification`
- `MembershipRenewalReminderNotification`
- `MembershipReminderNotification`

**Dependencies**:
- Stripe (via Laravel Cashier)
- **Support**: `MoneyCast`

**Provides**:
- `PaymentProcessorInterface`
- `CreditManagerInterface`

### Equipment Module ✅ COMPLETE

**Owns**: Equipment tracking and loans

**Models**:
- `Equipment`
- `EquipmentLoan`
- `EquipmentDamageReport`

**Key Actions**: 6 actions for checkout/return

**DTOs** (domain-specific):
- `EquipmentData`

**Dependencies**:
- **Support**: `HasTimePeriod` (for loans), `MoneyCast` (for values)

### Sponsorship Module ✅ COMPLETE

**Owns**: Corporate/organizational sponsorships

**Models**: `Sponsor`

**Key Actions**: 3 actions for sponsor management

### Moderation Module ✅ COMPLETE

**Owns**: Content moderation, trust system, reports, revisions

**Models**:
- `ContentModel` (abstract base)
- `Report`
- `Revision`
- `TrustTransaction`
- `UserTrustBalance`
- `TrustAchievement`

**Actions**:
- Trust actions (~10)
- Report actions (~4)
- Revision actions (~11)

**Concerns**:
- `Reportable`
- `Revisionable`
- `HasTrust`

**DTOs** (domain-specific):
- `SpamCheckResultData`

**Used By**: Events, Bands, MemberProfiles (any content that can be reported/revised)

### Support Module (Library)

**Owns**: Infrastructure components used across multiple domains

**This is a library module, not a domain module.** It contains reusable utilities with no business logic.

**Models**:
- `RecurringSeries` - scheduling pattern used by Events and Reservations

**Concerns** (truly cross-cutting):
- `HasTimePeriod` - used by Event, Reservation, EquipmentLoan (3+ modules)
- `HasRecurringSeries` - trait for models that use recurring series

**Casts**:
- `MoneyCast` - used by Reservation, Subscription, Equipment (3+ modules)

**Enums**:
- `RecurringSeriesStatus`

**Why these belong here**: Each is used by 3+ modules with identical logic. They're infrastructure, not domain logic.

---

## Distribution Summary

### Notifications → Domain Modules

| Notification | Target Module |
|--------------|---------------|
| Reservation* | SpaceManagement |
| Event* | Events |
| User*, Band*, Membership*, Password*, Email* | Membership |
| Membership*Reminder, MembershipExpired | Finance |

### DTOs → Domain Modules

| DTO | Target Module |
|-----|---------------|
| `LocationData`, `VenueLocationData` | Events |
| `UserSettingsData`, `ContactData` | Membership |
| `EquipmentData` | Equipment |
| `SpamCheckResultData` | Moderation |

### Concerns → Domain Modules

| Concern | Target Module | Reason |
|---------|---------------|--------|
| `HasPaymentStatus` | SpaceManagement | Only used by Reservation |
| `HasPublishing` | Events | Only used by Event |
| `HasPoster` | Events | Only used by Event |
| `HasCredits` | Finance | Only used for user credits |
| `HasMembershipStatus` | Membership | Only used by User |
| `HasTimePeriod` | **Support** | Used by 3+ modules |
| `HasRecurringSeries` | **Support** | Used by 2+ modules |

### Casts → Support Module

| Cast | Target Module | Reason |
|------|---------------|--------|
| `MoneyCast` | **Support** | Used by 3+ modules |

---

## Removed from Plan

### GoogleCalendar Integration
- **Status**: Unused, future uncertain
- **Action**: Do not migrate, leave in place or remove entirely
- **Location**: `app/Actions/GoogleCalendar/`

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
```

### Problem 3: ContentModel Inheritance (Trust/Reports/Revisions)

**Current**: All content types extend ContentModel

**Solution**: Keep in Moderation Module

This is genuinely shared behavior that applies identical logic across all content types. The moderation module explicitly owns this cross-cutting concern.

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
```

### 3. Support Module (Common Utilities)

Use for genuinely shared infrastructure:

```php
// Any module can use MoneyCast
use CorvMC\Support\Casts\MoneyCast;

protected function casts(): array {
    return ['cost' => MoneyCast::class . ':USD'];
}

// Any module can use RecurringSeries
use CorvMC\Support\Models\RecurringSeries;
```

---

## Filament Resource Organization

### Hybrid Approach: Module Components + Panel Customization

**Module-Level Components** (reusable):
```
app-modules/space-management/src/Filament/
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
```

---

## Migration Strategy

### Phase 1: Install & Setup ✅ COMPLETE

- [x] Install internachi/modular
- [x] Configure namespace to "CorvMC"
- [x] Run `php artisan modules:sync`
- [x] Create initial module scaffolds

### Phase 2: Equipment Module ✅ COMPLETE

- [x] Move models, actions, migrations
- [x] Update namespaces

### Phase 3: Migrate Domain Modules

**Order**: Sponsorship ✅ → SpaceManagement ✅ → Events ✅ → Membership ✅ → Finance

**Process per module**:
1. Create module scaffold if not exists
2. Move models to `app-modules/{module}/src/Models/`
3. Move actions to `app-modules/{module}/src/Actions/`
4. Move domain-specific concerns to `app-modules/{module}/src/Concerns/`
5. Move domain-specific DTOs to `app-modules/{module}/src/Data/`
6. Move domain-specific notifications to `app-modules/{module}/src/Notifications/`
7. Move migrations to `app-modules/{module}/database/migrations/`
8. Update all namespaces to `CorvMC\{Module}\`
9. Find/replace imports
10. Test module functionality

**SpaceManagement specifics**:
- Move all Reservation STI classes together
- Move `HasPaymentStatus` concern
- Move all Reservation* notifications
- Note: `RecurringReservation` is deprecated, do not migrate

**Events specifics**:
- Move Event and Venue models
- Move `HasPublishing`, `HasPoster` concerns
- Move `LocationData`, `VenueLocationData` DTOs
- Move all Event* notifications

**Membership specifics**:
- Move User, MemberProfile, Band, BandMember, StaffProfile, Invitation
- Move `HasMembershipStatus` concern
- Move `UserSettingsData`, `ContactData` DTOs
- Move all User*, Band*, membership notifications
- Keep auth working (User model must be discoverable)

**Finance specifics**:
- Move subscription and credit models
- Move `HasCredits` concern
- Move Membership*Reminder notifications
- Keep Cashier integration working

### Phase 4: Create Support Module ✅ COMPLETE

**Goal**: Consolidate truly cross-cutting infrastructure

**Moved to `app-modules/support/src/`**:
- `Models/RecurringSeries.php`
- `Concerns/HasTimePeriod.php`
- `Concerns/HasRecurringSeries.php`
- `Casts/MoneyCast.php`
- `Enums/RecurringSeriesStatus.php`

**Namespace**: `CorvMC\Support\`

### Phase 5: Moderation Module ✅ COMPLETE

Already migrated as standalone module.

### Phase 6: Decouple & Refactor

**Tasks**:
1. Create interface contracts in domain modules
2. Implement service provider bindings
3. Add domain events and listeners
4. Replace direct dependencies with interfaces

### Phase 7: Final Cleanup

**Tasks**:
1. Remove any class aliases
2. Update hardcoded namespace references
3. Run full test suite
4. Cache modules for production
5. Update `CLAUDE.md` with module architecture

---

## Directory Structure (Final State)

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
├── app-modules/
│   ├── space-management/
│   │   ├── src/
│   │   │   ├── Models/          # Reservation, RehearsalReservation, EventReservation
│   │   │   ├── Actions/         # ~43 actions
│   │   │   ├── Concerns/        # HasPaymentStatus
│   │   │   ├── Notifications/   # Reservation notifications
│   │   │   ├── Contracts/       # ReservationManagerInterface
│   │   │   └── Services/        # ReservationManager, ConflictDetector
│   │   └── database/migrations/
│   │
│   ├── events/
│   │   ├── src/
│   │   │   ├── Models/          # Event, Venue
│   │   │   ├── Actions/         # ~16 actions
│   │   │   ├── Concerns/        # HasPublishing, HasPoster
│   │   │   ├── Data/            # LocationData, VenueLocationData
│   │   │   └── Notifications/   # Event notifications
│   │   └── database/migrations/
│   │
│   ├── membership/
│   │   ├── src/
│   │   │   ├── Models/          # User, MemberProfile, Band, etc.
│   │   │   ├── Actions/         # ~23 actions
│   │   │   ├── Concerns/        # HasMembershipStatus
│   │   │   ├── Data/            # UserSettingsData, ContactData
│   │   │   └── Notifications/   # User/Band notifications
│   │   └── database/migrations/
│   │
│   ├── finance/
│   │   ├── src/
│   │   │   ├── Models/          # Subscription, Credits, PromoCode
│   │   │   ├── Actions/         # ~26 actions
│   │   │   ├── Concerns/        # HasCredits
│   │   │   ├── Contracts/       # CreditManagerInterface
│   │   │   └── Notifications/   # Membership reminder notifications
│   │   └── database/migrations/
│   │
│   ├── equipment/               # ✅ COMPLETE
│   │   ├── src/
│   │   │   ├── Models/
│   │   │   ├── Actions/
│   │   │   └── Data/            # EquipmentData
│   │   └── database/migrations/
│   │
│   ├── sponsorship/             # ✅ COMPLETE
│   │   ├── src/
│   │   │   ├── Models/
│   │   │   └── Actions/
│   │   └── database/migrations/
│   │
│   ├── moderation/              # ✅ COMPLETE
│   │   ├── src/
│   │   │   ├── Models/          # ContentModel, Report, Revision, Trust*
│   │   │   ├── Actions/         # Trust, Reports, Revisions actions
│   │   │   ├── Concerns/        # Reportable, Revisionable, HasTrust
│   │   │   └── Data/            # SpamCheckResultData
│   │   └── database/migrations/
│   │
│   └── support/                 # Library module
│       ├── src/
│       │   ├── Models/          # RecurringSeries
│       │   ├── Concerns/        # HasTimePeriod, HasRecurringSeries
│       │   ├── Casts/           # MoneyCast
│       │   └── Enums/           # RecurringSeriesStatus
│       └── tests/
│
├── composer.json                # Path repositories for all modules
└── CLAUDE.md                    # Updated with module info
```

---

## Success Criteria

✅ All modules have explicit service providers
✅ No direct cross-module model imports (use interfaces)
✅ Notifications distributed to domain modules
✅ DTOs distributed to domain modules
✅ Domain-specific concerns in their modules
✅ Support module contains only true cross-cutting infrastructure
✅ Critical flow tests pass after each phase
✅ All Filament panels work without disruption
✅ PHPStan level 2 maintained or improved

---

## Testing Strategy

### Approach: Hybrid Testing

The migration is mostly mechanical (moving files, updating namespaces). PHP will fail loudly if imports are broken. The real risk isn't subtle logic bugs—it's broken references.

**Three-phase testing approach:**

1. **Before migration**: Critical flow tests (feature tests for key business flows)
2. **During migration**: Run critical flow tests after each module
3. **After migration**: Add unit tests to modules in their final location

### Critical Flow Tests ✅ CREATED

Location: `tests/Feature/CriticalFlowsTest.php`

These tests verify the 4 core business flows continue working. They test external behavior through Actions, not internal structure, so they survive namespace changes without modification.

| Flow | Tests | What's Verified |
|------|-------|-----------------|
| **Reservation + Credits** | 4 | Sustaining members use free hours, partial credits charged, non-member pricing, conflict prevention |
| **Event + Conflicts** | 4 | CMC venue creation, conflict detection with reservations, external venue bypass, GetAllConflicts action |
| **Band + Invitations** | 4 | Band creation with owner, invitation sending, acceptance workflow, duplicate prevention |
| **Credit Allocation** | 4 | Monthly allocation, reset behavior, mid-month upgrades, deduction on reservation |

**Run after each migration phase:**
```bash
php artisan test tests/Feature/CriticalFlowsTest.php
```

### Why Feature Tests Over Unit Tests (During Migration)

| Aspect | Feature Tests | Unit Tests |
|--------|--------------|------------|
| **Namespace sensitivity** | Low - test through Actions | High - import specific classes |
| **Migration maintenance** | None - test behavior | High - update imports constantly |
| **What they catch** | Broken flows, integration issues | Broken individual components |
| **When to write** | Before migration | After migration |

### Testing Workflow Per Phase

```
┌─────────────────────────────────────────────────────────────┐
│  1. Start phase                                             │
│     └─> Run critical flow tests (baseline)                  │
│                                                             │
│  2. Move files to module                                    │
│     └─> Update namespaces                                   │
│     └─> Run `composer dump-autoload`                        │
│                                                             │
│  3. Verify                                                  │
│     └─> Run critical flow tests                             │
│     └─> Run PHPStan                                         │
│     └─> Manual smoke test Filament panels                   │
│                                                             │
│  4. If tests fail                                           │
│     └─> Fix broken imports/references                       │
│     └─> Re-run tests                                        │
│                                                             │
│  5. Phase complete                                          │
│     └─> Commit changes                                      │
└─────────────────────────────────────────────────────────────┘
```

### Unit Tests (Post-Migration)

After all modules are in place, add unit tests to each module:

```
app-modules/space-management/tests/
├── Unit/
│   ├── Actions/
│   │   ├── CreateReservationTest.php
│   │   ├── CalculateReservationCostTest.php
│   │   └── GetAllConflictsTest.php
│   └── Models/
│       └── ReservationTest.php
└── Feature/
    └── ReservationFlowTest.php
```

**Benefits of post-migration unit tests:**
- Tests live alongside code they test
- No namespace churn during migration
- Module-level test isolation
- Can run per-module: `php artisan test --filter=SpaceManagement`

### Existing Tests

Some tests already exist and should continue passing:

```
tests/Feature/Actions/Credits/           # Credit system tests
tests/Feature/Actions/Events/            # Event update tests
tests/Feature/Actions/Reservations/      # Payment handling tests
tests/Feature/Actions/Sponsors/          # Sponsorship tests
tests/Feature/Actions/Revisions/         # Revision coalescing tests
tests/Unit/Models/SponsorTest.php        # Sponsor model tests
tests/Unit/Tenancy/BandTenancyTest.php   # Band tenancy tests
```

**During migration**: Update imports in these tests as modules move, or temporarily use class aliases.

### Test Commands

```bash
# Run critical flow tests (primary migration verification)
php artisan test tests/Feature/CriticalFlowsTest.php

# Run all tests
composer test

# Run tests for specific module (post-migration)
php artisan test app-modules/space-management/tests/

# Run with coverage
composer test:coverage
```

---

## Verification Checklist

After each phase:

1. **Run critical flow tests**:
   ```bash
   php artisan test tests/Feature/CriticalFlowsTest.php
   ```

2. **Run full test suite** (if time permits):
   ```bash
   composer test
   ```

3. **Run static analysis**:
   ```bash
   vendor/bin/phpstan analyse
   ```

4. **Manual smoke test** (quick verification):
   - Member panel: Navigate to reservations
   - Create a test reservation (don't save)
   - View events list
   - View band profiles

5. **Check for runtime errors**:
   ```bash
   php artisan route:list  # Verify routes resolve
   php artisan config:cache && php artisan config:clear  # Verify config
   ```

---

## Recommendation

**Proceed with internachi/modular** for modular architecture with:

✅ Domain modules own their notifications, DTOs, and concerns
✅ Support module contains only true library components
✅ Moderation as standalone module for content moderation
✅ No "shared" catch-all module

**Next step**: Migrate SpaceManagement module (largest, validates complex patterns)

**Sources:**
- [InterNACHI/modular GitHub](https://github.com/InterNACHI/modular)
- [Laravel News: Modularize your Laravel apps](https://laravel-news.com/package/internachi-modular)
