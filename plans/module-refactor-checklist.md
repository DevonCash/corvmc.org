# Module Refactor Checklist

Based on [module-refactor.md](./module-refactor.md). Updated: 2026-01-21

---

## Testing Strategy

### Critical Flow Tests ✅ CREATED

Location: `tests/Feature/CriticalFlowsTest.php` (16 tests)

| Flow | Tests | Verifies |
|------|-------|----------|
| Reservation + Credits | 4 | Free hours, partial credits, pricing, conflicts |
| Event + Conflicts | 4 | CMC venue, conflict detection, external bypass |
| Band + Invitations | 4 | Creation, invites, acceptance, duplicates |
| Credit Allocation | 4 | Monthly reset, upgrades, deduction |

### Per-Phase Verification

Run after completing each phase:

```bash
# 1. Critical flow tests (required)
php artisan test tests/Feature/CriticalFlowsTest.php

# 2. Static analysis (required)
vendor/bin/phpstan analyse

# 3. Full test suite (if time permits)
composer test
```

### Post-Migration Unit Tests

After all modules are migrated, add unit tests to each module's `tests/` directory.

---

## Phase 1: Install & Setup ✅ COMPLETE

- [x] Install internachi/modular package
- [x] Publish config (`config/app-modules.php`)
- [x] Configure namespace to "CorvMC"
- [x] Configure vendor to "corvmc"
- [x] Run `php artisan modules:sync`

---

## Phase 2: Equipment Module ✅ COMPLETE

### Models

- [x] `Equipment.php` → `app-modules/equipment/src/Models/`
- [x] `EquipmentLoan.php` → `app-modules/equipment/src/Models/`
- [x] `EquipmentDamageReport.php` → `app-modules/equipment/src/Models/`

### Actions (6 total)

- [x] `CheckoutToMember.php`
- [x] `GetStatistics.php`
- [x] `GetValueByAcquisitionType.php`
- [x] `MarkOverdue.php`
- [x] `MarkReturnedToOwner.php`
- [x] `ProcessReturn.php`

### Migrations

- [x] All equipment migrations moved

### DTOs

- [ ] `EquipmentData.php` → `app-modules/equipment/src/Data/`

### Verification

- [ ] Critical flow tests pass: `php artisan test tests/Feature/CriticalFlowsTest.php`
- [ ] PHPStan passes: `vendor/bin/phpstan analyse`

---

## Phase 3: Sponsorship Module ✅ COMPLETE

### Models

- [x] `Sponsor.php` → `app-modules/sponsorship/src/Models/`

### Actions (3 total)

- [x] `AssignSponsoredMembership.php`
- [x] `GetSponsorAvailableSlots.php`
- [x] `RevokeSponsoredMembership.php`

### Migrations

- [x] All sponsorship migrations moved

---

## Phase 4: Moderation Module ✅ COMPLETE

### Models

- [x] `ContentModel.php`
- [x] `Report.php`
- [x] `Revision.php`
- [x] `TrustTransaction.php`
- [x] `UserTrustBalance.php`
- [x] `TrustAchievement.php`

### Actions

- [x] Trust actions (~10)
- [x] Report actions (~4)
- [x] Revision actions (~11)

### Concerns

- [ ] `Reportable.php` → `app-modules/moderation/src/Concerns/`
- [ ] `Revisionable.php` → `app-modules/moderation/src/Concerns/`
- [ ] `HasTrust.php` → `app-modules/moderation/src/Concerns/`

### DTOs

- [ ] `SpamCheckResultData.php` → `app-modules/moderation/src/Data/`

### Migrations

- [x] All moderation migrations moved

---

## Phase 5: SpaceManagement Module ⏳ NOT STARTED

### Models

- [ ] `Reservation.php` (base class, STI)
- [ ] `RehearsalReservation.php`
- [ ] `EventReservation.php`
- [ ] ~~`RecurringReservation.php`~~ (deprecated - do not migrate)

### Actions (~43 total)

From `app/Actions/Reservations/`:

- [ ] Conflict detection actions
- [ ] Cost calculation actions
- [ ] Availability checking actions

From `app/Actions/RecurringReservations/`:

- [ ] `CancelRecurringSeries.php`
- [ ] `CreateRecurringRehearsal.php`
- [ ] `ExtendRecurringSeries.php`
- [ ] `GenerateRecurringInstances.php`
- [ ] `GetUpcomingRecurringInstances.php`
- [ ] `PauseRecurringSeries.php`
- [ ] `ResumeRecurringSeries.php`
- [ ] `SkipRecurringInstance.php`
- [ ] `UpdateRecurringSeries.php`

### Concerns (domain-specific)

- [ ] `HasPaymentStatus.php` → `app-modules/space-management/src/Concerns/`

### Notifications (8 total)

- [ ] `ReservationCreatedNotification.php`
- [ ] `ReservationConfirmedNotification.php`
- [ ] `ReservationCancelledNotification.php`
- [ ] `ReservationReminderNotification.php`
- [ ] `ReservationConfirmationNotification.php`
- [ ] `ReservationConfirmationReminderNotification.php`
- [ ] `ReservationAutoCancelledNotification.php`
- [ ] `ReservationCreatedTodayNotification.php`
- [ ] `DailyReservationDigestNotification.php`

### Migrations

- [ ] All reservation-related migrations

### Contracts to Create

- [ ] `ReservationManagerInterface`
- [ ] `ConflictDetectorInterface`
- [ ] `PricingCalculatorInterface`

### Policies

- [ ] `RecurringSeriesPolicy.php` → `app-modules/space-management/src/Policies/`

### Verification

- [ ] Critical flow tests pass: `php artisan test tests/Feature/CriticalFlowsTest.php`
- [ ] PHPStan passes: `vendor/bin/phpstan analyse`

---

## Phase 6: Events Module ⏳ NOT STARTED

### Models

- [ ] `Event.php`
- [ ] `Venue.php`

### Actions (~16 total)

From `app/Actions/Events/`:

- [ ] Event CRUD actions
- [ ] Publishing workflow actions
- [ ] Performer management actions
- [ ] Rescheduling actions

### Concerns (domain-specific)

- [ ] `HasPublishing.php` → `app-modules/events/src/Concerns/`
- [ ] `HasPoster.php` → `app-modules/events/src/Concerns/`

### DTOs (domain-specific)

- [ ] `LocationData.php` → `app-modules/events/src/Data/`
- [ ] `VenueLocationData.php` → `app-modules/events/src/Data/`

### Notifications (4 total)

- [ ] `EventCreatedNotification.php`
- [ ] `EventUpdatedNotification.php`
- [ ] `EventCancelledNotification.php`
- [ ] `EventPublishedNotification.php`

### Migrations

- [ ] All event-related migrations

### Verification

- [ ] Critical flow tests pass: `php artisan test tests/Feature/CriticalFlowsTest.php`
- [ ] PHPStan passes: `vendor/bin/phpstan analyse`

---

## Phase 7: Membership Module ⏳ NOT STARTED

### Models

- [ ] `User.php`
- [ ] `MemberProfile.php`
- [ ] `Band.php`
- [ ] `BandMember.php`
- [ ] `StaffProfile.php`
- [ ] `Invitation.php`

### Actions

From `app/Actions/Users/`:

- [ ] User management actions (~5)

From `app/Actions/MemberProfiles/`:

- [ ] Member profile actions (~9)

From `app/Actions/Bands/`:

- [ ] Band operations actions (~9)

From `app/Actions/Invitations/`:

- [ ] Invitation system actions (~10)

From `app/Actions/StaffProfiles/`:

- [ ] Staff profile actions (~11)

### Concerns (domain-specific)

- [ ] `HasMembershipStatus.php` → `app-modules/membership/src/Concerns/`

### DTOs (domain-specific)

- [ ] `UserSettingsData.php` → `app-modules/membership/src/Data/`
- [ ] `ContactData.php` → `app-modules/membership/src/Data/`

### Notifications (11 total)

- [ ] `NewMemberWelcomeNotification.php`
- [ ] `UserCreatedNotification.php`
- [ ] `UserUpdatedNotification.php`
- [ ] `UserDeactivatedNotification.php`
- [ ] `UserInvitationNotification.php`
- [ ] `EmailVerificationNotification.php`
- [ ] `PasswordResetNotification.php`
- [ ] `BandInvitationNotification.php`
- [ ] `BandInvitationAcceptedNotification.php`
- [ ] `BandOwnershipInvitationNotification.php`
- [ ] `ContactFormSubmissionNotification.php`

### Migrations

- [ ] All user/member/band-related migrations

### Contracts to Create

- [ ] `MemberRepositoryInterface`

### Special Considerations

- [ ] Keep auth working (User model must be discoverable)
- [ ] Update `config/auth.php` if needed

### Verification

- [ ] Critical flow tests pass: `php artisan test tests/Feature/CriticalFlowsTest.php`
- [ ] PHPStan passes: `vendor/bin/phpstan analyse`
- [ ] Auth works: Login/logout flow

---

## Phase 8: Finance Module ⏳ NOT STARTED

### Models

- [ ] `Subscription.php`
- [ ] `UserCredit.php`
- [ ] `CreditTransaction.php`
- [ ] `CreditAllocation.php`
- [ ] `PromoCode.php`
- [ ] `PromoCodeRedemption.php`

### Actions

From `app/Actions/Payments/`:

- [ ] Payment calculation actions (~10)

From `app/Actions/Subscriptions/`:

- [ ] Subscription management actions (~9)

From `app/Actions/Credits/`:

- [ ] Credit system actions (~4)

From `app/Actions/MemberBenefits/`:

- [ ] Member benefits actions (~3)

### Concerns (domain-specific)

- [ ] `HasCredits.php` → `app-modules/finance/src/Concerns/`

### Notifications (3 total)

- [ ] `MembershipExpiredNotification.php`
- [ ] `MembershipRenewalReminderNotification.php`
- [ ] `MembershipReminderNotification.php`

### Migrations

- [ ] All subscription/credit-related migrations

### Contracts to Create

- [ ] `PaymentProcessorInterface`
- [ ] `CreditManagerInterface`

### Special Considerations

- [ ] Keep Cashier integration working

### Verification

- [ ] Critical flow tests pass: `php artisan test tests/Feature/CriticalFlowsTest.php`
- [ ] PHPStan passes: `vendor/bin/phpstan analyse`

---

## Phase 9: Support Module ✅ COMPLETE

### Models

- [x] `RecurringSeries.php` → `app-modules/support/src/Models/`

### Concerns (truly cross-cutting)

- [x] `HasTimePeriod.php` → `app-modules/support/src/Concerns/`
- [x] `HasRecurringSeries.php` → `app-modules/support/src/Concerns/`

### Casts

- [x] `MoneyCast.php` → `app-modules/support/src/Casts/`

### Enums

- [x] `RecurringSeriesStatus.php` → `app-modules/support/src/Enums/`

### Create Module

- [x] Run `php artisan make:module support`
- [x] Configure composer.json

### Verification

- [x] Critical flow tests pass: `php artisan test tests/Feature/CriticalFlowsTest.php`
- [x] PHPStan passes: `vendor/bin/phpstan analyse` (pre-existing issues only)

---

## Phase 10: Decouple & Refactor ⏳ NOT STARTED

### Interface Contracts

- [ ] Create `CreditManagerInterface` in finance module
- [ ] Create `PaymentProcessorInterface` in finance module
- [ ] Create `ReservationManagerInterface` in space-management module
- [ ] Create `MemberRepositoryInterface` in membership module

### Service Provider Bindings

- [ ] Finance: Bind `CreditManagerInterface`
- [ ] Finance: Bind `PaymentProcessorInterface`
- [ ] SpaceManagement: Bind `ReservationManagerInterface`
- [ ] Membership: Bind `MemberRepositoryInterface`

### Domain Events

- [ ] Create `MembershipStatusChanged` event
- [ ] Create `ReservationCreated` event
- [ ] Create `EventPublished` event

### Event Listeners

- [ ] Create `AllocateCreditsOnMembershipChange` listener
- [ ] Create `DeductCreditsOnReservation` listener

### Replace Direct Dependencies

- [ ] Replace direct model imports with interfaces
- [ ] Update action dependencies to use interfaces

### Verification

- [ ] Critical flow tests pass: `php artisan test tests/Feature/CriticalFlowsTest.php`
- [ ] PHPStan passes: `vendor/bin/phpstan analyse`

---

## Phase 11: Final Cleanup ⏳ NOT STARTED

### Class Aliases

- [ ] Remove any aliases from `app/Models/`

### Namespace Updates

- [ ] Update hardcoded namespace references
- [ ] Verify all imports use new namespaces

### Production Optimization

- [ ] Run `php artisan modules:cache`

### Documentation

- [ ] Update `CLAUDE.md` with module architecture
- [ ] Document module structure
- [ ] Document module-specific commands

### Static Analysis

- [ ] Verify PHPStan passes at current level

---

## Removed from Plan

### GoogleCalendar Integration

- **Status**: Unused, future uncertain
- **Action**: Do not migrate
- **Location**: `app/Actions/GoogleCalendar/` (leave in place or remove)

---

## Final Verification Checklist

### Automated Tests

- [ ] Critical flow tests: `php artisan test tests/Feature/CriticalFlowsTest.php`
- [ ] Full test suite: `composer test`
- [ ] PHPStan: `vendor/bin/phpstan analyse`

### Manual Smoke Tests

- [ ] Filament: Member panel navigation works
- [ ] Filament: Band tenant switching works
- [ ] Filament: Admin functions accessible

### Infrastructure

- [ ] Routes resolve: `php artisan route:list`
- [ ] Config valid: `php artisan config:cache && php artisan config:clear`
- [ ] Modules listed: `php artisan modules:list`

### Post-Migration: Add Unit Tests

- [ ] SpaceManagement module unit tests
- [ ] Events module unit tests
- [ ] Membership module unit tests
- [ ] Finance module unit tests
- [ ] Support module unit tests

---

## Progress Summary

| Phase | Module | Status | Notes |
|-------|--------|--------|-------|
| 1 | Setup | ✅ Complete | |
| 2 | Equipment | ✅ Complete | DTO still in app/Data |
| 3 | Sponsorship | ✅ Complete | |
| 4 | Moderation | ✅ Complete | Concerns still in app/Concerns |
| 5 | SpaceManagement | ⏳ Not Started | Largest module (~43 actions) |
| 6 | Events | ⏳ Not Started | ~16 actions |
| 7 | Membership | ⏳ Not Started | ~44 actions, includes User |
| 8 | Finance | ⏳ Not Started | ~26 actions |
| 9 | Support | ✅ Complete | RecurringSeries, MoneyCast, HasTimePeriod |
| 10 | Decouple | ⏳ Not Started | Interfaces, events |
| 11 | Cleanup | ⏳ Not Started | |

**Next recommended step**: Migrate SpaceManagement module (largest, validates complex patterns)
